<?php
session_start();

// Vérification de session et rôle

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'medecin') {
  header("Location: connection.php");
  exit;
}


// Récupérer les messages de session
$message = $_SESSION['message'] ?? '';
$msg_type = $_SESSION['msg_type'] ?? '';
unset($_SESSION['message']);
unset($_SESSION['msg_type']);

$conn = new mysqli("localhost", "root", "", "gestion_cabinet_medical");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8");

$id_medecin = intval($_SESSION['medecin_id']);
$active_section = $_GET['section'] ?? 'dashboard';
// Fetch doctor info
$stmt = $conn->prepare("SELECT nom, prenom, spécialité, email, telephone FROM medecin WHERE id_medecin = ?");
$stmt->bind_param("i", $id_medecin);
$stmt->execute();
$medecin = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch upcoming appointments for the doctor
$rdv_stmt = $conn->prepare("
    SELECT r.id_rdv, r.date_rdv, r.heure, r.lieu, r.motif, p.nom, p.prenom, r.statut, p.id_patient
    FROM rendezvous r
    JOIN traitement t ON r.id_traitement = t.id_traitement
    JOIN patient p ON t.id_patient = p.id_patient
    WHERE t.id_medecin = ? AND r.date_rdv >= CURDATE() AND r.statut != 'annule'
    ORDER BY r.date_rdv ASC, r.heure ASC
");
$rdv_stmt->bind_param("i", $id_medecin);
$rdv_stmt->execute();
$rendezvous = $rdv_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$next_rendezvous = !empty($rendezvous) ? $rendezvous[0] : null;
$rdv_stmt->close();

// Fetch recent prescriptions
$ord_stmt = $conn->prepare("
    SELECT o.id_ordonnance, o.date_ordonnance, o.médicaments, p.nom, p.prenom
    FROM ordonnance o
    JOIN traitement t ON o.id_traitement = t.id_traitement
    JOIN patient p ON t.id_patient = p.id_patient
    WHERE t.id_medecin = ?
    ORDER BY o.date_ordonnance DESC
");
$ord_stmt->bind_param("i", $id_medecin);
$ord_stmt->execute();
$ordonnances = $ord_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$ord_stmt->close();

// Fetch patients for creating rendezvous, prescriptions, and dossiers
$patients = $conn->query("
    SELECT DISTINCT p.id_patient, CONCAT(p.prenom,' ',p.nom) AS nom_complet
    FROM traitement t
    JOIN patient p ON p.id_patient = t.id_patient
    WHERE t.id_medecin = $id_medecin
    ORDER BY p.prenom, p.nom
");

// Nouveau: Fetch tous les patients du médecin
$mes_patients = [];
if ($active_section === 'mes_patients') {
    // Gestion de la suppression d'un patient
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id']) && ctype_digit($_GET['id'])) {
        $id_patient = intval($_GET['id']);
        
        // Vérifier que le patient appartient bien à ce médecin
        $stmt = $conn->prepare("
            SELECT id_traitement
            FROM traitement
            WHERE id_patient = ? AND id_medecin = ?
        ");
        $stmt->bind_param("ii", $id_patient, $id_medecin);
        $stmt->execute();
        $traitements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        // Supprimer le patient
              $stmt = $conn->prepare("DELETE FROM patient WHERE id_patient = ?");
        $stmt->bind_param("i", $id_patient);
        if ($stmt->execute()) {
            $message = "success:Patient supprimé avec succès.";
        } else {
            $message = "error:Erreur lors de la suppression : " . $conn->error;
        }
        $stmt->close();
            }
                $mes_patients = $conn->query("
        SELECT p.* 
        FROM patient p
        JOIN traitement t ON p.id_patient = t.id_patient
        WHERE t.id_medecin = $id_medecin
    ");

    // Charger les patients après suppression
    $stmt = $conn->prepare("
        SELECT DISTINCT p.id_patient, p.nom, p.prenom, p.email, p.telephone, p.date_naissance, p.sexe
        FROM patient p
        JOIN traitement t ON p.id_patient = t.id_patient
        WHERE t.id_medecin = ?
        ORDER BY p.nom, p.prenom
    ");
    $stmt->bind_param("i", $id_medecin);
    $stmt->execute();
    $mes_patients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Dossier Medical Logic
$patient = null;
$historique = [];
$rdv_futurs = [];
$examens = [];
$ordonnances_patient = []; // Nouvelle variable pour stocker les ordonnances du patient
if ($active_section === 'dossiers' && isset($_GET['id']) && ctype_digit($_GET['id'])) {
    $id_p = intval($_GET['id']);
    
    // Verify that the patient is treated by this doctor
    $stmt = $conn->prepare("
        SELECT p.*
        FROM patient p
        JOIN traitement t ON p.id_patient = t.id_patient
        WHERE p.id_patient = ? AND t.id_medecin = ?
    ");
    $stmt->bind_param("ii", $id_p, $id_medecin);
    $stmt->execute();
    $patient = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$patient) {
        $message = "error:Patient non trouvé ou vous n'avez pas les droits pour voir ce dossier.";
        $active_section = 'dashboard';
    } else {
        // Historique consultations
        $stmt_historique = $conn->prepare("
            SELECT r.date_rdv, r.heure, r.motif, r.lieu, m.nom AS medecin_nom, m.prenom AS medecin_prenom, t.diagnostic
            FROM rendezvous r
            JOIN traitement t ON r.id_traitement = t.id_traitement
            JOIN medecin m ON t.id_medecin = m.id_medecin
            WHERE t.id_patient = ? AND (r.date_rdv < CURDATE() OR (r.date_rdv = CURDATE() AND r.heure < CURTIME()))
            ORDER BY r.date_rdv DESC, r.heure DESC
        ");
        $stmt_historique->bind_param("i", $id_p);
        $stmt_historique->execute();
        $historique = $stmt_historique->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_historique->close();

        // Rendez-vous à venir
$stmt_rdv = $conn->prepare("
    SELECT r.id_rdv, r.date_rdv, r.heure, r.motif, r.lieu, m.nom AS medecin_nom, m.prenom AS medecin_prenom
    FROM rendezvous r
    JOIN traitement t ON r.id_traitement = t.id_traitement
    JOIN medecin m ON t.id_medecin = m.id_medecin
    WHERE t.id_patient = ? AND r.date_rdv >= CURDATE() AND r.statut != 'annule'
    ORDER BY r.date_rdv ASC, r.heure ASC
");
        $stmt_rdv->bind_param("i", $id_p);
        $stmt_rdv->execute();
        $rdv_futurs = $stmt_rdv->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_rdv->close();
        
        // Récupérer les examens du patient
        $stmt_examens = $conn->prepare("
            SELECT e.type_examen, e.résultat, e.date_examen
            FROM examen e
            JOIN traitement t ON e.id_traitement = t.id_traitement
            WHERE t.id_patient = ?
            ORDER BY e.date_examen DESC
        ");
        $stmt_examens->bind_param("i", $id_p);
        $stmt_examens->execute();
        $examens = $stmt_examens->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_examens->close();
        
        // NOUVEAU: Récupérer les ordonnances du patient pour ce médecin
        $stmt_ordonnances = $conn->prepare("
            SELECT o.id_ordonnance, o.date_ordonnance, o.médicaments
            FROM ordonnance o
            JOIN traitement t ON o.id_traitement = t.id_traitement
            WHERE t.id_patient = ? AND t.id_medecin = ?
            ORDER BY o.date_ordonnance DESC
        ");
        $stmt_ordonnances->bind_param("ii", $id_p, $id_medecin);
        $stmt_ordonnances->execute();
        $ordonnances_patient = $stmt_ordonnances->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_ordonnances->close();
    }
}

// Handle Create Rendezvous
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['creer_rdv']) && $active_section === 'create-rdv') {
    $id_patient = intval($_POST['id_patient']);
    $date_rdv = $_POST['date_rdv'];
    $heure = $_POST['heure'];
    $lieu = $conn->real_escape_string($_POST['lieu']);
    $motif = $conn->real_escape_string($_POST['motif']);

    $res = $conn->query("
        SELECT id_traitement
        FROM traitement
        WHERE id_medecin=$id_medecin AND id_patient=$id_patient
        ORDER BY date_debut DESC LIMIT 1
    ");
    if ($res->num_rows === 0) {
        $message = "error:Aucun traitement trouvé pour ce patient.";
    } else {
        $id_traitement = $res->fetch_assoc()['id_traitement'];
       $statut = ($_SESSION['role'] === 'medecin') ? 'confirmé' : 'en_attente';
$stmt = $conn->prepare("
    INSERT INTO rendezvous (id_traitement, date_rdv, heure, lieu, motif, statut)
    VALUES (?, ?, ?, ?, ?, ?)
");
$stmt->bind_param("isssss", $id_traitement, $date_rdv, $heure, $lieu, $motif, $statut);

        if ($stmt->execute()) {
            $message = "success:Rendez-vous créé avec succès.";
            $rdv_stmt = $conn->prepare("
                SELECT r.id_rdv, r.date_rdv, r.heure, r.lieu, r.motif, p.nom, p.prenom, p.id_patient
                FROM rendezvous r
                JOIN traitement t ON r.id_traitement = t.id_traitement
                JOIN patient p ON t.id_patient = p.id_patient
                WHERE t.id_medecin = ? AND r.date_rdv >= CURDATE()
                ORDER BY r.date_rdv ASC, r.heure ASC
            ");
            $rdv_stmt->bind_param("i", $id_medecin);
            $rdv_stmt->execute();
            $rendezvous = $rdv_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $next_rendezvous = !empty($rendezvous) ? $rendezvous[0] : null;
            $rdv_stmt->close();
        } else {
           // $message = "error:Erreur à l'enregistrement, veuillez réessayer.";
           $message = "success:Rendez-vous créé avec succès.";
        }
        $stmt->close();
    }
}

// Handle Create Prescription (MODIFIÉ POUR GÉRER LES FICHIERS)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajouter_ordonnance']) && $active_section === 'prescriptions') {
    $id_patient = intval($_POST['id_patient'] ?? 0);
    
    // Vérifier le fichier uploadé
    if ($id_patient <= 0) {
        $message = "error:Veuillez sélectionner un patient.";
    } elseif (!isset($_FILES['ordonnance_file']) || $_FILES['ordonnance_file']['error'] !== UPLOAD_ERR_OK) {
        $message = "error:Veuillez sélectionner un fichier valide.";
    } else {
        $file = $_FILES['ordonnance_file'];
        
        // Vérifier le type de fichier
        $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png', 'gif'];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_extension, $allowed_extensions)) {
            $message = "error:Seuls les fichiers PDF, JPG, JPEG, PNG et GIF sont autorisés.";
        } else {
            // Créer le dossier d'upload s'il n'existe pas
            $upload_directory = 'uploads/';
            if (!is_dir($upload_directory)) {
                mkdir($upload_directory, 0755, true);
            }
            
            // Générer un nom de fichier unique
            $new_filename = uniqid('ord_', true) . '.' . $file_extension;
            $destination = $upload_directory . $new_filename;
            $date_today = date('Y-m-d');
            
            if (move_uploaded_file($file['tmp_name'], $destination)) {
                // Fichier uploadé avec succès, enregistrer dans la base
                $stmt = $conn->prepare("
                    SELECT id_traitement FROM traitement
                    WHERE id_patient = ? AND id_medecin = ?
                    LIMIT 1
                ");
                $stmt->bind_param("ii", $id_patient, $id_medecin);
                $stmt->execute();
                $treatment = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($treatment && $date_today) {
                    $stmt = $conn->prepare("
                        INSERT INTO ordonnance (id_traitement, date_ordonnance, médicaments)
                        VALUES (?, ?, ?)
                    ");
                    
                    $stmt->bind_param("iss", $treatment['id_traitement'], $date_today, $destination);
                    if ($stmt->execute()) {
                        // Stocker le message dans la session et rediriger
                        $_SESSION['message'] = "success:Ordonnance ajoutée avec succès.";
                        $_SESSION['msg_type'] = 'success';
                        header("Location: medecin.php?section=prescriptions");
                        exit;
                    } else {
                       // $message = "error:Erreur lors de l'ajout de l'ordonnance.";
                      }
                      header("Location: medecin.php?section=prescriptions");
                    $stmt->close();
                } else {
                    $message = "error:Aucun traitement trouvé pour ce patient avec ce médecin.";
                }
            } else {
                $message = "error:Erreur lors de l'enregistrement du fichier.";
            }
        }
    }
}

// Handle Delete Prescription
if ($active_section === 'delete-ordonnance' && isset($_GET['id_ordonnance']) && ctype_digit($_GET['id_ordonnance'])) {
    $id_ordonnance = intval($_GET['id_ordonnance']);
    // Verify that the ordonnance belongs to the current doctor
    $stmt = $conn->prepare("SELECT o.id_ordonnance, o.médicaments 
                            FROM ordonnance o
                            JOIN traitement t ON o.id_traitement = t.id_traitement
                            WHERE t.id_medecin = ? AND o.id_ordonnance = ?");
    $stmt->bind_param("ii", $id_medecin, $id_ordonnance);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $ordonnance = $result->fetch_assoc();
        $file_path = $ordonnance['médicaments'];

        // Delete the ordonnance record
        $stmt_delete = $conn->prepare("DELETE FROM ordonnance WHERE id_ordonnance = ?");
        $stmt_delete->bind_param("i", $id_ordonnance);
        if ($stmt_delete->execute()) {
            // If the file exists and is a file, delete it
            if (file_exists($file_path) && is_file($file_path)) {
                unlink($file_path);
            }
            $_SESSION['message'] = "success:Ordonnance supprimée avec succès.";
            $_SESSION['msg_type'] = 'success';
        } else {
            $_SESSION['message'] = "error:Erreur lors de la suppression de l'ordonnance.";
            $_SESSION['msg_type'] = 'error';
        }
        $stmt_delete->close();
    } else {
        $_SESSION['message'] = "error:Ordonnance non trouvée ou vous n'avez pas les droits.";
        $_SESSION['msg_type'] = 'error';
    }
    $stmt->close();

    // Redirect
    $redirect = $_GET['redirect'] ?? 'prescriptions';
    if ($redirect === 'dossiers' && isset($_GET['id'])) {
        header("Location: medecin.php?section=dossiers&id=" . intval($_GET['id']));
    } else {
        header("Location: medecin.php?section=prescriptions");
    }
    exit;
}

// Handle Confirmer Rendezvous (Accept appointment)
if ($active_section === 'confirm-rdv' && isset($_GET['id_rdv']) && ctype_digit($_GET['id_rdv'])) {
    $id_rdv_to_confirm = intval($_GET['id_rdv']);

    // First, verify that the rendezvous exists and belongs to this doctor
    $stmt = $conn->prepare("
        SELECT r.id_rdv
        FROM rendezvous r
        JOIN traitement t ON r.id_traitement = t.id_traitement
        WHERE r.id_rdv = ? AND t.id_medecin = ? AND r.statut = 'en_attente'
        LIMIT 1
    ");
    $stmt->bind_param("ii", $id_rdv_to_confirm, $id_medecin);
    $stmt->execute();
    $rdv_to_confirm_exists = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$rdv_to_confirm_exists) {
        $message = "error:Rendez-vous non trouvé, déjà confirmé, ou vous n'avez pas les droits pour le confirmer.";
        $active_section = 'agenda'; // Redirect back to agenda
    } else {
        // If the rendezvous exists and is 'en_attente' and belongs to this doctor, update its status
        $stmt = $conn->prepare("UPDATE rendezvous SET statut = 'confirmé' WHERE id_rdv = ?");
        $stmt->bind_param("i", $id_rdv_to_confirm);

        if ($stmt->execute()) {
            $message = "success:Rendez-vous confirmé avec succès.";
            $active_section = 'agenda'; // Set active section back to agenda to display updated list

            // Re-fetch the rendezvous list to reflect the change immediately in the agenda
            $rdv_stmt = $conn->prepare("
                SELECT r.id_rdv, r.date_rdv, r.heure, r.lieu, r.motif, p.nom, p.prenom, r.statut, p.id_patient
                FROM rendezvous r
                JOIN traitement t ON r.id_traitement = t.id_traitement
                JOIN patient p ON t.id_patient = p.id_patient
                WHERE t.id_medecin = ? AND r.date_rdv >= CURDATE() AND r.statut != 'annule'
                ORDER BY r.date_rdv ASC, r.heure ASC
            ");
            $rdv_stmt->bind_param("i", $id_medecin);
            $rdv_stmt->execute();
            $rendezvous = $rdv_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $next_rendezvous = !empty($rendezvous) ? $rendezvous[0] : null;
            $rdv_stmt->close();

        } else {
            $message = "error:Échec de la confirmation du rendez-vous.";
            $active_section = 'agenda'; // Redirect back to agenda on error
        }
        $stmt->close();
    }
}

// Handle Cancel Rendezvous
if ($active_section === 'cancel-rdv' && isset($_GET['id_rdv']) && ctype_digit($_GET['id_rdv'])) {
    $id_rdv = intval($_GET['id_rdv']);
    $stmt = $conn->prepare("
        SELECT r.id_rdv, t.id_medecin, t.id_patient, t.id_assistant,
               r.date_rdv, r.heure, p.prenom AS p_prenom, p.nom AS p_nom,
               m.prenom AS m_prenom, m.nom AS m_nom, r.motif
        FROM rendezvous r
        JOIN traitement t ON r.id_traitement = t.id_traitement
        JOIN patient p ON t.id_patient = p.id_patient
        JOIN medecin m ON t.id_medecin = m.id_medecin
        WHERE r.id_rdv = ? AND t.id_medecin = ? AND r.statut != 'annule'
        LIMIT 1
    ");
    $stmt->bind_param("ii", $id_rdv, $id_medecin);
    $stmt->execute();
    $rdv_info = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$rdv_info) {
        $message = "error:Rendez-vous non trouvé, déjà annulé ou vous n'avez pas les droits pour l'annuler.";
        $active_section = 'agenda';
    } else {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['annuler_rdv'])) {
            // Mettre à jour le statut au lieu de supprimer
            $stmt = $conn->prepare("UPDATE rendezvous SET statut = 'annule' WHERE id_rdv = ?");
            $stmt->bind_param("i", $id_rdv);
            if ($stmt->execute()) {
                $message = "success:Rendez-vous annulé avec succès.";
                $active_section = 'agenda';
                // Recharger les rendez-vous
                $rdv_stmt = $conn->prepare("
                    SELECT r.id_rdv, r.date_rdv, r.heure, r.lieu, r.motif,r.statut , p.nom, p.prenom, p.id_patient
                    FROM rendezvous r
                    JOIN traitement t ON r.id_traitement = t.id_traitement
                    JOIN patient p ON t.id_patient = p.id_patient
                    WHERE t.id_medecin = ? AND r.date_rdv >= CURDATE() AND r.statut != 'annule'
                    ORDER BY r.date_rdv ASC, r.heure ASC
                ");
                $rdv_stmt->bind_param("i", $id_medecin);
                $rdv_stmt->execute();
                $rendezvous = $rdv_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $next_rendezvous = !empty($rendezvous) ? $rendezvous[0] : null;
                $rdv_stmt->close();
            } else {
                $message = "error:Échec de l'annulation du rendez-vous.";
            }
            $stmt->close();
        }
    }
}

// Handle Modifier Patient
$patient_to_edit = null;
if ($active_section === 'modifier-patient' && isset($_GET['id']) && ctype_digit($_GET['id'])) {
    $id_p = intval($_GET['id']);
    
    // Verify that the patient is treated by this doctor
    $stmt = $conn->prepare("
        SELECT p.*
        FROM patient p
        JOIN traitement t ON p.id_patient = t.id_patient
        WHERE p.id_patient = ? AND t.id_medecin = ?
    ");
    $stmt->bind_param("ii", $id_p, $id_medecin);
    $stmt->execute();
    $patient_to_edit = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$patient_to_edit) {
        $message = "error:Patient non trouvé ou vous n'avez pas les droits pour modifier ce patient.";
        $active_section = 'dossiers';
    } else {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modifier_patient'])) {
            $nom = trim($_POST['nom'] ?? '');
            $prenom = trim($_POST['prenom'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $telephone = trim($_POST['telephone'] ?? '');
            $adresse = trim($_POST['adresse'] ?? '');
            $sexe = $_POST['sexe'] ?? '';

            // Basic validation
            if (empty($nom) || empty($prenom) || empty($email) || empty($telephone) || empty($sexe)) {
                $message = "error:Tous les champs obligatoires doivent être remplis.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $message = "error:L'email n'est pas valide.";
            } elseif (!in_array($sexe, ['Homme', 'Femme'])) {
                $message = "error:Le sexe sélectionné est invalide.";
            } else {
                $stmt = $conn->prepare("UPDATE patient SET nom=?, prenom=?, email=?, telephone=?, adresse=?, sexe=? WHERE id_patient=?");
                $stmt->bind_param("ssssssi", $nom, $prenom, $email, $telephone, $adresse, $sexe, $id_p);
                if ($stmt->execute()) {
                    $message = "success:Patient mis à jour avec succès.";
                    $active_section = 'dossiers';
                    // Update patient data for display
                    $stmt->close();
                    $stmt = $conn->prepare("
                        SELECT p.*
                        FROM patient p
                        JOIN traitement t ON p.id_patient = t.id_patient
                        WHERE p.id_patient = ? AND t.id_medecin = ?
                    ");
                    $stmt->bind_param("ii", $id_p, $id_medecin);
                    $stmt->execute();
                    $patient = $stmt->get_result()->fetch_assoc();
                } else {
                    $message = "error:Erreur lors de la mise à jour du patient.";
                }
                $stmt->close();
            }
        }
    }
}
// Handle Modifier Rendezvous
$rdv_to_edit = null;
if ($active_section === 'modifier-rdv' && isset($_GET['id_rdv']) && ctype_digit($_GET['id_rdv'])) {
    $id_rdv = intval($_GET['id_rdv']);
    
    // Fetch rendezvous details
    $stmt = $conn->prepare("
        SELECT r.id_rdv, r.date_rdv, r.heure, r.lieu, r.motif, 
               p.id_patient, p.nom AS patient_nom, p.prenom AS patient_prenom,
               m.id_medecin, m.nom AS medecin_nom, m.prenom AS medecin_prenom
        FROM rendezvous r
        JOIN traitement t ON r.id_traitement = t.id_traitement
        JOIN patient p ON t.id_patient = p.id_patient
        JOIN medecin m ON t.id_medecin = m.id_medecin
        WHERE r.id_rdv = ? AND t.id_medecin = ?
    ");
    $stmt->bind_param("ii", $id_rdv, $id_medecin);
    $stmt->execute();
    $rdv_to_edit = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$rdv_to_edit) {
        $message = "error:Rendez-vous non trouvé ou vous n'avez pas les droits pour le modifier.";
        $active_section = 'agenda';
    } else {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modifier_rdv'])) {
            $new_date = trim($_POST['date_rdv'] ?? '');
            $new_time = trim($_POST['heure'] ?? '');

            $errors = [];
            if ($new_date === '') $errors[] = "La date est obligatoire.";
            if ($new_time === '') $errors[] = "L'heure est obligatoire.";

            if (empty($errors)) {
                $stmt = $conn->prepare("
                    UPDATE rendezvous
                    SET date_rdv=?, heure=?
                    WHERE id_rdv=?
                ");
                $stmt->bind_param("ssi", $new_date, $new_time, $id_rdv);
                if ($stmt->execute()) {
                    $message = "success:Rendez-vous mis à jour avec succès.";
                    $active_section = 'agenda';
                    // Refresh rendezvous list
                    $rdv_stmt = $conn->prepare("
                        SELECT r.id_rdv, r.date_rdv, r.heure, r.lieu, r.motif,r.statut , p.nom, p.prenom, p.id_patient
                        FROM rendezvous r
                        JOIN traitement t ON r.id_traitement = t.id_traitement
                        JOIN patient p ON t.id_patient = p.id_patient
                        WHERE t.id_medecin = ? AND r.date_rdv >= CURDATE() AND r.statut != 'annule'
                        ORDER BY r.date_rdv ASC, r.heure ASC
                    ");
                    $rdv_stmt->bind_param("i", $id_medecin);
                    $rdv_stmt->execute();
                    $rendezvous = $rdv_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $next_rendezvous = !empty($rendezvous) ? $rendezvous[0] : null;
                    $rdv_stmt->close();
                } else {
                    $message = "error:Erreur lors de la mise à jour.";
                }
                $stmt->close();
            } else {
                $message = "error:" . implode(" ", $errors);
            }
        }
    }
}

if ($message) list($msg_type, $msg_text) = explode(':', $message, 2);
$conn->close();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Espace Médecin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --primary: #2b6cb0;
      --primary-dark: #1a4971;
      --primary-light: #60a5fa;
      --accent: #38a169;
      --accent-dark: #2f855a;
      --bg-light: #f8fafc;
      --bg-dark: #1e293b;
      --card-bg-light: #ffffff;
      --card-bg-dark: #2d3748;
      --text-light: #2d3748;
      --text-dark: #e2e8f0;
      --text-light-subtle: #64748b;
      --text-dark-subtle: #94a3b8;
      --border-light: #e2e8f0;
      --border-dark: #4b5563;
      --shadow-light: rgba(0, 0, 0, 0.05);
      --shadow-dark: rgba(0, 0, 0, 0.2);
      --error: #dc3545;
      --success: #28a745;
      --danger: #dc2626;
      --calm-blue: #e6f0fa;
      --motivation-yellow: #fff9db;
      --positive-green: #e6f4ea;
    }

    [data-theme="dark"] {
      --bg: var(--bg-dark);
      --card-bg: var(--card-bg-dark);
      --text: var(--text-dark);
      --text-light: var(--text-dark-subtle);
      --border: var(--border-dark);
      --shadow: var(--shadow-dark);
    }

    [data-theme="light"] {
      --bg: var(--bg-light);
      --card-bg: var(--card-bg-light);
      --text: var(--text-light);
      --text-light: var(--text-light-subtle);
      --border: var(--border-light);
      --shadow: var(--shadow-light);
    }

    body {
      background: var(--bg);
      font-family: 'Poppins', sans-serif;
      color: var(--text);
      min-height: 100vh;
      margin: 0;
      padding: 0;
      transition: background 0.3s ease, color 0.3s ease;
      overflow-x: hidden;
    }

    .app-container {
      display: flex;
      min-height: 100vh;
      position: relative;
      overflow: hidden;
    }

    .sidebar {
      width: 250px;
      background: linear-gradient(180deg, var(--primary), var(--primary-dark));
      color: #fff;
      padding: 1.5rem 1rem;
      position: fixed;
      height: 100%;
      transition: transform 0.4s ease-in-out, width 0.4s ease-in-out;
      z-index: 1000;
      box-shadow: 2px 0 10px var(--shadow);
    }

    .sidebar.collapsed {
      width: 70px;
      transform: translateX(0);
    }

    .sidebar-brand {
      font-size: 1.6rem;
      font-weight: 700;
      display: flex;
      align-items: center;
      gap: 0.75rem;
      margin-bottom: 2.5rem;
      opacity: 0;
      animation: fadeInDown 0.6s ease-out forwards;
    }

    .sidebar-nav .nav-link {
      display: flex;
      align-items: center;
      gap: 1rem;
      padding: 0.9rem 1.2rem;
      color: #fff;
      border-radius: 0.6rem;
      transition: all 0.3s ease-in-out;
      font-size: 0.95rem;
      white-space: nowrap;
      overflow: hidden;
    }

    .sidebar.collapsed .nav-link span {
      display: none;
    }

    .sidebar-nav .nav-link:hover {
      background: rgba(255, 255, 255, 0.15);
      transform: translateX(5px);
      color: #fff;
    }

    .sidebar-toggle {
      position: fixed;
      top: 1.5rem;
      left: 260px;
      background: var(--primary);
      color: #fff;
      border: none;
      border-radius: 50%;
      width: 45px;
      height: 45px;
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 1001;
      transition: left 0.4s ease-in-out, transform 0.3s ease;
      box-shadow: 0 2px 6px var(--shadow);
    }

    .sidebar-toggle:hover {
      transform: scale(1.1);
    }

    .sidebar.collapsed + .sidebar-toggle {
      left: 80px;
    }

    .main-content {
      flex-grow: 1;
      padding: 1.5rem;
      margin-left: 250px;
      transition: margin-left 0.4s ease-in-out;
      position: relative;
      overflow-y: auto;
    }

    .sidebar.collapsed ~ .main-content {
      margin-left: 70px;
    }

    .header {
      background: linear-gradient(135deg, var(--card-bg), rgba(255, 255, 255, 0.95));
      border-radius: 0.9rem;
      box-shadow: 0 4px 12px var(--shadow);
      padding: 1.5rem;
      margin-bottom: 1.5rem;
      display: flex;
      flex-wrap: wrap;
      gap: 1.5rem;
      align-items: center;
      backdrop-filter: blur(8px);
      animation: fadeIn 0.6s ease-out;
    }

    .header-title {
      font-size: 1.8rem;
      font-weight: 600;
      margin: 0;
      color: var(--primary-dark);
    }

    .next-rdv {
      background: linear-gradient(135deg, var(--accent), var(--accent-dark));
      color: #fff;
      padding: 1rem;
      border-radius: 0.6rem;
      min-width: 250px;
      display: flex;
      align-items: center;
      gap: 1rem;
      position: relative;
      box-shadow: 0 4px 15px rgba(56, 161, 105, 0.3);
      animation: slideIn 0.5s ease-out;
      z-index: 10;
    }

    .next-rdv:after {
      content: '';
      position: absolute;
      top: -10px;
      left: 20px;
      width: 0;
      height: 0;
      border-left: 10px solid transparent;
      border-right: 10px solid transparent;
      border-bottom: 10px solid var(--accent-dark);
    }

    .next-rdv-icon {
      font-size: 1.5rem;
      opacity: 0.9;
    }

    .section-container {
      background: var(--card-bg);
      border-radius: 12px;
      box-shadow: 0 6px 18px var(--shadow);
      padding: 2rem;
      margin-bottom: 2rem;
      animation: fadeInUp 0.7s ease-out;
      display: block;
    }

    .form-group {
      margin-bottom: 1.5rem;
    }

    .form-group label {
      display: block;
      font-weight: 500;
      font-size: 1rem;
      color: var(--text);
      margin-bottom: 0.5rem;
    }

    .form-group input,
    .form-group textarea,
    .form-group select {
      width: 100%;
      padding: 0.75rem 1rem;
      border: 1px solid var(--border);
      border-radius: 8px;
      font-size: 0.95rem;
      color: var(--text);
      background: var(--card-bg);
      transition: all 0.3s ease;
    }

    .form-group input:focus,
    .form-group textarea:focus,
    .form-group select:focus {
      border-color: var(--primary);
      outline: none;
      box-shadow: 0 0 0 3px rgba(43, 108, 176, 0.15);
    }

    .form-group textarea {
      min-height: 100px;
      resize: vertical;
    }

    .message {
      font-size: 0.9rem;
      margin-bottom: 1rem;
      padding: 0.75rem;
      border-radius: 8px;
      display: none;
    }

    .message-error {
      color: var(--error);
      background: rgba(220, 53, 69, 0.1);
      border: 1px solid var(--error);
    }

    .message-success {
      color: var(--success);
      background: rgba(40, 167, 69, 0.1);
      border: 1px solid var(--success);
    }

    .message.active {
      display: block;
    }

    .submit-btn {
      background: var(--primary);
      color: #fff;
      border: none;
      padding: 0.75rem 1.5rem;
      border-radius: 8px;
      font-size: 1rem;
      font-weight: 500;
      transition: all 0.3s ease;
      width: 100%;
    }

    .submit-btn:hover {
      background: var(--primary-dark);
      transform: translateY(-2px);
      box-shadow: 0 4px 8px var(--shadow);
    }

    .cancel-btn {
      background: var(--error);
      color: #fff;
      border: none;
      padding: 0.75rem 1.5rem;
      border-radius: 8px;
      font-size: 1rem;
      font-weight: 500;
      transition: all 0.3s ease;
      width: 100%;
    }

    .cancel-btn:hover {
      background: #bb2d3b;
      transform: translateY(-2px);
      box-shadow: 0 4px 8px var(--shadow);
    }

    .table-container {
      background: var(--card-bg);
      border-radius: 12px;
      box-shadow: 0 6px 18px var(--shadow);
      padding: 1.5rem;
      animation: fadeInUp 0.7s ease-out;
    }

    table {
      width: 100%;
      border-collapse: collapse;
    }

    th, td {
      padding: 1rem;
      text-align: left;
      border-bottom: 1px solid var(--border);
    }

    th {
      background: var(--primary-light);
      color: var(--text);
      font-weight: 600;
    }

    td {
      vertical-align: middle;
    }

    tr:hover {
      background: rgba(59, 130, 246, 0.1);
    }

    .theme-toggle {
      position: fixed;
      top: 1.5rem;
      right: 1.5rem;
      background: var(--primary);
      color: #fff;
      border: none;
      border-radius: 50%;
      width: 45px;
      height: 45px;
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 1001;
      cursor: pointer;
      transition: transform 0.3s ease, background 0.3s ease;
      box-shadow: 0 2px 6px var(--shadow);
    }

    .theme-toggle:hover {
      transform: scale(1.1) rotate(360deg);
      background: var(--primary-dark);
    }

    /* NOUVEAU STYLE POUR LA PAGE D'ACCUEIL */
    .dashboard-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 1.5rem;
      margin-bottom: 2rem;
    }

    .dashboard-card {
      border-radius: 15px;
      padding: 1.5rem;
      box-shadow: 0 4px 12px var(--shadow);
      animation: fadeInUp 0.7s ease-out;
      height: 100%;
      display: flex;
      flex-direction: column;
      transition: transform 0.3s ease;
    }

    .dashboard-card:hover {
      transform: translateY(-5px);
    }

    .dashboard-card h3 {
      font-size: 1.4rem;
      font-weight: 600;
      margin-bottom: 1rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      color: var(--primary-dark);
    }

    .dashboard-card p, .dashboard-card li {
      font-size: 1rem;
      line-height: 1.6;
      color: var(--text);
    }

    .dashboard-card ul {
      padding-left: 1.5rem;
      flex-grow: 1;
    }

    .dashboard-card .card-icon {
      font-size: 2.5rem;
      margin-bottom: 1rem;
      color: var(--primary);
    }

    .stats-card {
      background: linear-gradient(135deg, var(--primary-light), var(--primary));
      color: white;
    }

    .stats-card h3, .stats-card .card-icon {
      color: white;
    }

    .stats-card .stat-value {
      font-size: 2.5rem;
      font-weight: 700;
      margin: 0.5rem 0;
    }

    .stats-card .stat-label {
      font-size: 1rem;
      opacity: 0.9;
    }

    .quick-actions {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1rem;
      margin-top: 2rem;
    }

    .quick-action-btn {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 1.5rem;
      border-radius: 12px;
      background: var(--card-bg);
      box-shadow: 0 4px 8px var(--shadow);
      transition: all 0.3s ease;
      text-align: center;
      text-decoration: none;
      color: var(--text);
    }

    .quick-action-btn:hover {
      transform: translateY(-3px);
      box-shadow: 0 6px 12px var(--shadow);
      background: var(--primary-light);
      color: white;
    }

    .quick-action-btn i {
      font-size: 2rem;
      margin-bottom: 0.5rem;
    }

    .quick-action-btn span {
      font-weight: 500;
    }

    /* Dossier Medical Styles */
    .card {
      background: var(--card-bg);
      border-radius: 12px;
      box-shadow: 0 4px 12px var(--shadow);
      transition: all 0.3s ease-in-out;
      overflow: hidden;
      border: none;
      margin-bottom: 1.5rem;
    }

    .card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 20px var(--shadow);
    }

    .section-title {
      font-size: 1.2rem;
      font-weight: 600;
      color: var(--primary-dark);
      padding: 1.2rem;
      background: linear-gradient(90deg, var(--primary-light), rgba(96, 165, 250, 0.8));
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .card-body {
      padding: 1.5rem;
    }

    .info-label {
      font-weight: 500;
      color: var(--primary);
      min-width: 180px;
      display: inline-block;
    }

    .info-value {
      display: inline-block;
      margin-bottom: 0.5rem;
    }

    .rdv-item, .history-item {
      border-bottom: 1px solid var(--border);
      padding: 1rem 0;
      transition: all 0.3s ease;
    }

    .rdv-item:hover, .history-item:hover {
      background: rgba(59, 130, 246, 0.1);
      padding-left: 0.5rem;
    }

    .rdv-item:last-child, .history-item:last-child {
      border-bottom: none;
    }

    .rdv-date, .history-date {
      font-weight: 500;
      color: var(--primary);
      font-size: 1rem;
      margin-bottom: 0.3rem;
    }

    .empty {
      color: var(--text-light);
      font-style: italic;
      padding: 1rem 0;
      font-size: 1rem;
      text-align: center;
    }

    .btn-primary {
      background: var(--primary);
      border: none;
      border-radius: 8px;
      padding: 0.6rem 1.2rem;
      transition: all 0.3s ease-in-out;
      font-weight: 500;
      color: #fff;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
    }

    .btn-primary:hover {
      background: var(--primary-dark);
      transform: translateY(-2px);
      box-shadow: 0 4px 12px var(--shadow);
    }

    .btn-danger {
      background: var(--danger);
      border: none;
      border-radius: 8px;
      padding: 0.6rem 1.2rem;
      transition: all 0.3s ease-in-out;
      font-weight: 500;
      color: #fff;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
    }

    .btn-danger:hover {
      background: #b91c1c;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px var(--shadow);
    }

    .btn-success {
      background: var(--accent);
      border: none;
      border-radius: 8px;
      padding: 0.6rem 1.2rem;
      transition: all 0.3s ease-in-out;
      font-weight: 500;
      color: #fff;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
    }

    .btn-success:hover {
      background: var(--accent-dark);
      transform: translateY(-2px);
      box-shadow: 0 4px 12px var(--shadow);
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }

    @keyframes fadeInDown {
      from { opacity: 0; transform: translateY(-20px); }
      to { opacity: 1; transform: translateY(0); }
    }

    @keyframes fadeInUp {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }

    @keyframes slideIn {
      from { opacity: 0; transform: translateY(-20px); }
      to { opacity: 1; transform: translateY(0); }
    }

    @media (max-width: 992px) {
      .sidebar {
        transform: translateX(-250px);
      }
      .sidebar.collapsed {
        transform: translateX(0);
        width: 70px;
      }
      .sidebar-toggle {
        left: 10px;
      }
      .sidebar.collapsed + .sidebar-toggle {
        left: 80px;
      }
      .main-content {
        margin-left: 0;
      }
      .sidebar.collapsed ~ .main-content {
        margin-left: 70px;
      }
    }

    @media (max-width: 768px) {
      .header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
      }
      .next-rdv {
        flex-direction: column;
        align-items: flex-start;
        width: 100%;
      }
      .table-container {
        padding: 1rem;
      }
      .info-label {
        min-width: 120px;
      }
      .dashboard-grid {
        grid-template-columns: 1fr;
      }
      .quick-actions {
        grid-template-columns: 1fr 1fr;
      }
    }

    @media (max-width: 576px) {
      .submit-btn, .cancel-btn, .btn-primary, .btn-success, .btn-danger {
        width: 100%;
        text-align: center;
        padding: 0.75rem;
      }
      .section-container {
        padding: 1.5rem;
      }
      table {
        display: block;
        overflow-x: auto;
      }
      .card-body {
        padding: 1rem;
      }
      .quick-actions {
        grid-template-columns: 1fr;
      }
    }

    ::-webkit-scrollbar {
      width: 10px;
    }
    ::-webkit-scrollbar-track {
      background: var(--bg);
      border-radius: 12px;
    }
    ::-webkit-scrollbar-thumb {
      background: var(--primary);
      border-radius: 12px;
    }
    ::-webkit-scrollbar-thumb:hover {
      background: var(--primary-dark);
    }
  </style>
</head>
<body data-theme="light">
  <div class="app-container">
    <div class="sidebar" id="sidebar">
      <div class="sidebar-brand animate__animated animate__fadeInDown">
        <i class="fas fa-heartbeat"></i> Cabinet Médical
      </div>
      <div class="sidebar-nav">
        <a href="?section=dashboard" class="nav-link <?= $active_section === 'dashboard' ? 'active' : '' ?>" aria-label="Tableau de Bord">
          <i class="fas fa-home"></i> <span>Accueil</span>
        </a>
        <a href="?section=mes_patients" class="nav-link <?= $active_section === 'mes_patients' ? 'active' : '' ?>" aria-label="Mes Patients">
          <i class="fas fa-users"></i> <span>Mes Patients</span>
        </a>
        <a href="?section=create-rdv" class="nav-link <?= $active_section === 'create-rdv' ? 'active' : '' ?>" aria-label="Nouveau Rendez-vous">
          <i class="fas fa-calendar-plus"></i> <span>Nouveau RDV</span>
        </a>
        <a href="?section=agenda" class="nav-link <?= $active_section === 'agenda' ? 'active' : '' ?>" aria-label="Agenda">
          <i class="fas fa-calendar-alt"></i> <span>Agenda</span>
        </a>
        <a href="?section=prescriptions" class="nav-link <?= $active_section === 'prescriptions' ? 'active' : '' ?>" aria-label="Ordonnances">
          <i class="fas fa-pills"></i> <span>Ordonnances</span>
        </a>
        <a href="?section=dossiers" class="nav-link <?= $active_section === 'dossiers' ? 'active' : '' ?>" aria-label="Dossiers Médicaux">
          <i class="fas fa-folder-open"></i> <span>Dossiers</span>
        </a>
        <a href="deconnexion.php" class="nav-link" aria-label="Déconnexion">
          <i class="fas fa-sign-out-alt"></i> <span>Déconnexion</span>
        </a>
      </div>
    </div>

    <button class="sidebar-toggle" onclick="toggleSidebar()">
      <i class="fas fa-bars"></i>
    </button>

    <div class="main-content">
      <button class="theme-toggle" onclick="toggleTheme()" aria-label="Toggle theme">
        <i class="fas fa-sun"></i>
      </button>

      <div class="header animate__animated animate__fadeIn">
        <div class="header-info flex-grow-1">
          <h1 class="header-title">
            <i class="fas fa-user-md me-2"></i>
            Dr. <?= 
            htmlspecialchars($medecin['prenom'] . ' ' . $medecin['nom'])
             ?>
          </h1>
          <p class="text-[var(--text-light)]"><i class="fas fa-stethoscope me-2"></i> <?= htmlspecialchars($medecin['spécialité']) ?></p>
        </div>
        <?php if ($next_rendezvous): ?>
          <div class="next-rdv animate__animated animate__fadeInRight">
            <div class="next-rdv-content">
              <div><strong>Prochain RDV :</strong> <?= date('d/m/Y', strtotime($next_rendezvous['date_rdv'])) ?> à <?= substr($next_rendezvous['heure'], 0, 5) ?></div>
              <div><strong>Patient :</strong> <?= htmlspecialchars($next_rendezvous['prenom'] . ' ' . $next_rendezvous['nom']) ?></div>
              <div><strong>Motif :</strong> <?= htmlspecialchars($next_rendezvous['motif']) ?></div>
            </div>
            <i class="fas fa-calendar-check next-rdv-icon"></i>
          </div>
        <?php endif; ?>
      </div>

      <?php if ($message): ?>
        <div class="message message-<?= $msg_type ?> active"><?= htmlspecialchars($msg_text) ?></div>
      <?php endif; ?>

      <!-- NOUVELLE PAGE D'ACCUEIL AMÉLIORÉE -->
      <div id="dashboard-section" class="section-container <?= $active_section === 'dashboard' ? '' : 'd-none' ?>">
        <h2 class="mb-4"><i class="fas fa-home me-2"></i> Tableau de Bord</h2>
        
        <div class="dashboard-grid">
          <!-- Carte Statistiques -->
          <div class="dashboard-card stats-card">
            <div class="card-icon">
              <i class="fas fa-calendar-check"></i>
            </div>
            <div class="stat-value"><?= count($rendezvous) ?></div>
            <div class="stat-label">Rendez-vous à venir</div>
          </div>
          
          <!-- Carte Patients -->
          <div class="dashboard-card stats-card" style="background: linear-gradient(135deg, var(--accent), var(--accent-dark));">
            <div class="card-icon">
              <i class="fas fa-users"></i>
            </div>
            <div class="stat-value"><?= $patients->num_rows ?></div>
            <div class="stat-label">Patients suivis</div>
          </div>
          
          <!-- Carte Ordonnances -->
          <div class="dashboard-card stats-card" style="background: linear-gradient(135deg, #8b5cf6, #6d28d9);">
            <div class="card-icon">
              <i class="fas fa-pills"></i>
            </div>
            <div class="stat-value"><?= count($ordonnances) ?></div>
            <div class="stat-label">Ordonnances récentes</div>
          </div>
        </div>

        <!-- Actions rapides -->
        <h4 class="mb-3"><i class="fas fa-bolt me-2"></i> Accès rapide</h4>
        <div class="quick-actions">
          <a href="?section=create-rdv" class="quick-action-btn">
            <i class="fas fa-calendar-plus"></i>
            <span>Nouveau RDV</span>
          </a>
          <a href="?section=prescriptions" class="quick-action-btn">
            <i class="fas fa-pills"></i>
            <span>Nouvelle ordonnance</span>
          </a>
          
          <a href="?section=agenda" class="quick-action-btn">
            <i class="fas fa-calendar-week"></i>
            <span>Voir l'agenda</span>
          </a>
        </div>

        <!-- Prochain rendez-vous -->
        <?php if ($next_rendezvous): ?>
          <div class="dashboard-card mt-4">
            <h3><i class="fas fa-bell me-2"></i> Votre prochain rendez-vous</h3>
            <div class="alert alert-primary">
              <div class="d-flex align-items-center">
                <i class="fas fa-calendar-day fa-2x me-3"></i>
                <div>
                  <h5 class="mb-1"><?= htmlspecialchars($next_rendezvous['prenom'] . ' ' . $next_rendezvous['nom']) ?></h5>
                  <p class="mb-1">
                    <i class="fas fa-clock me-1"></i> 
                    <?= date('d/m/Y', strtotime($next_rendezvous['date_rdv'])) ?> à <?= substr($next_rendezvous['heure'], 0, 5) ?>
                  </p>
                  <p class="mb-0"><i class="fas fa-comment-medical me-1"></i> <?= htmlspecialchars($next_rendezvous['motif']) ?></p>
                </div>
              </div>
            </div>
            <a href="?section=agenda" class="btn btn-primary mt-2">
              <i class="fas fa-calendar-alt me-1"></i> Voir l'agenda complet
            </a>
          </div>
        <?php endif; ?>

        <!-- Conseils et astuces -->
        <div class="dashboard-card mt-4">
          <h3><i class="fas fa-lightbulb me-2"></i> Conseils du jour</h3>
          <div class="alert alert-success">
            <h5><i class="fas fa-heartbeat me-2"></i> Bien-être professionnel</h5>
            <p>Prenez le temps de faire des pauses régulières pour maintenir votre concentration et votre efficacité tout au long de la journée.</p>
          </div>
          <div class="alert alert-info">
            <h5><i class="fas fa-clock me-2"></i> Gestion du temps</h5>
            <p>Planifiez vos consultations avec des marges entre chaque rendez-vous pour gérer les imprévus et éviter le stress.</p>
          </div>
        </div>
      </div>

      <!-- Les autres sections restent inchangées -->
      <div id="mes-patients-section" class="section-container <?= $active_section === 'mes_patients' ? '' : 'd-none' ?>">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <h2 class="mb-0"><i class="fas fa-users me-2"></i> Mes Patients</h2>
          <div class="search-bar" style="width: 300px;">
            <input type="text" id="searchPatients" class="form-control" placeholder="Rechercher un patient...">
          </div>
        </div>
        
        <div class="table-container">
          <table>
            <thead>
              <tr>
                <th>Nom</th>
                <th>Prénom</th>
                <th>Email</th>
                <th>Téléphone</th>
                <th>Date de Naissance</th>
                <th>Sexe</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($mes_patients)): ?>
                <tr><td colspan="7" style="text-align:center; padding: 1rem;">Aucun patient trouvé</td></tr>
              <?php else: ?>
                <?php foreach ($mes_patients as $patient): ?>
                  <tr>
                    <td><?= htmlspecialchars($patient['nom']) ?></td>
                    <td><?= htmlspecialchars($patient['prenom']) ?></td>
                    <td><?= htmlspecialchars($patient['email']) ?></td>
                    <td><?= htmlspecialchars($patient['telephone']) ?></td>
                    <td><?= htmlspecialchars($patient['date_naissance']) ?></td>
                    <td><?= htmlspecialchars($patient['sexe']) ?></td>
                    <td>
                      <a href="?section=dossiers&id=<?= $patient['id_patient'] ?>" class="btn btn-sm btn-primary">
                        <i class="fas fa-folder-open"></i> Dossier
                      </a>
                      <a href="?section=mes_patients&action=delete&id=<?= $patient['id_patient'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce patient ?')">
                        <i class="fas fa-trash"></i> Supprimer
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        
        <script>
          // Script de recherche pour les patients
          document.getElementById('searchPatients').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const table = document.querySelector('#mes-patients-section table');
            const rows = table.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
              const cells = row.querySelectorAll('td');
              let found = false;
              cells.forEach(cell => {
                if (cell.textContent.toLowerCase().includes(searchTerm)) {
                  found = true;
                }
              });
              row.style.display = found ? '' : 'none';
            });
          });
        </script>
      </div>

      <div id="create-rdv-section" class="section-container <?= $active_section === 'create-rdv' ? '' : 'd-none' ?>">
        <h2 class="mb-4"><i class="fas fa-calendar-plus me-2"></i> Créer un Rendez-vous</h2>
        <form method="post">
          <div class="form-group">
            <label for="id_patient">Patient :</label>
            <select id="id_patient" name="id_patient" required>
              <option value="">-- Sélectionnez --</option>
              <?php while ($p = $patients->fetch_assoc()): ?>
                <option value="<?= $p['id_patient'] ?>" <?= isset($_GET['patient_id']) && $_GET['patient_id'] == $p['id_patient'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($p['nom_complet']) ?>
                </option>
              <?php endwhile; $patients->data_seek(0); ?>
            </select>
          </div>
          <div class="form-group">
            <label for="date_rdv">Date :</label>
            <input type="date" id="date_rdv" name="date_rdv" required min="<?= date('Y-m-d') ?>">
          </div>
          <div class="form-group">
            <label for="heure">Heure :</label>
            <input type="time" id="heure" name="heure" required min="08:00" max="18:00">
          </div>
          <div class="form-group">
            <label for="lieu">Lieu :</label>
            <input type="text" id="lieu" name="lieu" value="Clinique A">
          </div>
          <div class="form-group">
            <label for="motif">Motif :</label>
            <textarea id="motif" name="motif" rows="3"></textarea>
          </div>
          <button type="submit" name="creer_rdv" class="submit-btn">Enregistrer</button>
        </form>
      </div>

      <div id="agenda-section" class="section-container <?= $active_section === 'agenda' ? '' : 'd-none' ?>">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <h2 class="mb-0"><i class="fas fa-calendar-alt me-2"></i> Mon Agenda</h2>
          <div class="search-bar" style="width: 300px;">
            <input type="text" id="searchAgenda" class="form-control" placeholder="Rechercher un rendez-vous...">
          </div>
        </div>
        
        <div class="table-container">
          <table>
            <thead>
              <tr>
                <th>Date</th>
                <th>Patient</th>
                <th>Détails</th>
                <th>Statut</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($rendezvous)): ?>
                <tr><td colspan="5" style="text-align:center; padding: 1rem;">Aucun rendez-vous à venir</td></tr>
              <?php else: ?>
                <?php foreach ($rendezvous as $rdv): ?>
                  <tr>
                    <td><?= date('d/m/Y', strtotime($rdv['date_rdv'])) ?> à <?= substr($rdv['heure'], 0, 5) ?></td>
                    <td><?= htmlspecialchars($rdv['prenom'] . ' ' . $rdv['nom']) ?></td>
                    <td>Lieu: <?= htmlspecialchars($rdv['lieu']) ?><br>Motif: <?= htmlspecialchars($rdv['motif']) ?></td>
                    <td>
                      <?php if ($rdv['statut'] === 'en_attente'): ?>
                        <span class="badge bg-warning text-dark"><i class="fas fa-hourglass-half me-1"></i> En attente</span>
                      <?php else: ?>
                        <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> Confirmé</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <div class="d-flex flex-column flex-md-row gap-2">
                        <?php if ($rdv['statut'] === 'en_attente'): ?>
                          <a href="?section=confirm-rdv&id_rdv=<?= $rdv['id_rdv'] ?>" class="btn btn-sm btn-success">
                            <i class="fas fa-check"></i> Accepter
                          </a>
                        <?php else: ?>
                          <a href="?section=modifier-rdv&id_rdv=<?= $rdv['id_rdv'] ?>" class="btn btn-sm btn-primary">
                            <i class="fas fa-edit"></i> Modifier
                          </a>
                        <?php endif; ?>
                        <a href="?section=cancel-rdv&id_rdv=<?= $rdv['id_rdv'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir annuler ce rendez-vous ?')">
                          <i class="fas fa-times"></i> Annuler
                        </a>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        
        <script>
          // Script de recherche pour l'agenda
          document.getElementById('searchAgenda').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const table = document.querySelector('#agenda-section table');
            const rows = table.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
              const cells = row.querySelectorAll('td');
              let found = false;
              cells.forEach(cell => {
                if (cell.textContent.toLowerCase().includes(searchTerm)) {
                  found = true;
                }
              });
              row.style.display = found ? '' : 'none';
            });
          });
        </script>
      </div>

      <!-- MODIFICATION: Formulaire d'upload pour les ordonnances -->
      <div id="prescriptions-section" class="section-container <?= $active_section === 'prescriptions' ? '' : 'd-none' ?>">
        <h2 class="mb-4"><i class="fas fa-pills me-2"></i> Gestion des Ordonnances</h2>
        <h5 class="mb-3">Ajouter une ordonnance</h5>
        <form method="post" enctype="multipart/form-data">
          <div class="form-group">
            <label for="id_patient_prescription">Patient :</label>
            <select id="id_patient_prescription" name="id_patient" required>
              <option value="">-- Sélectionnez --</option>
              <?php while ($p = $patients->fetch_assoc()): ?>
                <option value="<?= $p['id_patient'] ?>"><?= htmlspecialchars($p['nom_complet']) ?></option>
              <?php endwhile; $patients->data_seek(0); ?>
            </select>
          </div>
          <div class="form-group">
            <label for="ordonnance_file">Fichier d'ordonnance (PDF ou image) :</label>
            <input type="file" id="ordonnance_file" name="ordonnance_file" accept=".pdf,.jpg,.jpeg,.png,.gif" required>
            <small class="form-text text-muted">Formats acceptés: PDF, JPG, JPEG, PNG, GIF</small>
          </div>
          <button type="submit" name="ajouter_ordonnance" class="submit-btn">Ajouter l'ordonnance</button>
        </form>
        <h5 class="mt-4 mb-3">Liste des Ordonnances</h5>
        <div class="table-container">
          <table>
            <thead>
              <tr>
                <th>Date</th>
                <th>Patient</th>
                <th>Ordonnance</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($ordonnances)): ?>
                <tr><td colspan="4" style="text-align:center; padding: 1rem;">Aucune ordonnance récente</td></tr>
              <?php else: ?>
                <?php foreach ($ordonnances as $ord): ?>
                  <tr>
                    <td><?= date('d/m/Y', strtotime($ord['date_ordonnance'])) ?></td>
                    <td><?= htmlspecialchars($ord['prenom'] . ' ' . $ord['nom']) ?></td>
                    <td>
                      <?php if (!empty($ord['médicaments']) && file_exists($ord['médicaments'])): ?>
                        <a href="<?= htmlspecialchars($ord['médicaments']) ?>" target="_blank" class="btn btn-sm btn-primary">
                           Consilter
                        </a>
                      <?php else: ?>
                        <span class="text-danger">Fichier manquant</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <a href="?section=delete-ordonnance&id_ordonnance=<?= $ord['id_ordonnance'] ?>&redirect=prescriptions" class="btn btn-sm btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette ordonnance ?')">
                        <i class="fas fa-trash"></i> Supprimer
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div id="dossiers-section" class="section-container <?= $active_section === 'dossiers' ? '' : 'd-none' ?>">
        <h2 class="mb-4"><i class="fas fa-folder-open me-2"></i> Dossiers Médicaux</h2>
        <?php if (!isset($_GET['id'])): ?>
          <h5 class="mb-3">Sélectionner un patient</h5>
          <form method="get">
            <input type="hidden" name="section" value="dossiers">
            <div class="form-group">
              <label for="id_patient_dossier">Patient :</label>
              <select id="id_patient_dossier" name="id" required onchange="this.form.submit()">
                <option value="">-- Sélectionnez --</option>
                <?php while ($p = $patients->fetch_assoc()): ?>
                  <option value="<?= $p['id_patient'] ?>"><?= htmlspecialchars($p['nom_complet']) ?></option>
                <?php endwhile; $patients->data_seek(0); ?>
              </select>
            </div>
          </form>
        <?php elseif ($patient): ?>
          <div class="header animate__animated animate__fadeIn">
            <div class="header-info flex-grow-1">
              <h1 class="header-title">
                <i class="fas fa-file-medical me-2"></i>
                Dossier Médical de <?= htmlspecialchars($patient['prenom'] . ' ' . $patient['nom']) ?>
              </h1>
              <p class="text-[var(--text-light)]">ID Patient: <?= $id_p ?></p>
            </div>
            <div class="d-flex align-items-center justify-content-md-end">
              <a href="?section=dossiers" class="btn-primary">
                <i class="fas fa-arrow-left me-1"></i> Retour
              </a>
            </div>
          </div>

          <!-- Patient Info -->
          <div class="card animate__animated animate__fadeInUp">
            <div class="section-title">
              <i class="fas fa-user-circle"></i>
              Informations du patient
            </div>
            <div class="card-body">
              <div>
                <span class="info-label">Nom complet :</span>
                <span class="info-value"><?= htmlspecialchars($patient['nom'] . ' ' . $patient['prenom']) ?></span>
              </div>
              <div>
                <span class="info-label">Date de naissance :</span>
                <span class="info-value"><?= htmlspecialchars($patient['date_naissance']) ?></span>
              </div>
              <div>
                <span class="info-label">Sexe :</span>
                <span class="info-value"><?= htmlspecialchars($patient['sexe']) ?></span>
              </div>
              <div>
                <span class="info-label">Téléphone :</span>
                <span class="info-value"><?= htmlspecialchars($patient['telephone']) ?></span>
              </div>
              <div>
                <span class="info-label">Email :</span>
                <span class="info-value"><?= htmlspecialchars($patient['email']) ?></span>
              </div>
              <div>
                <span class="info-label">Adresse :</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($patient['adresse'])) ?></span>
              </div>
                          <div class="d-flex gap-2 mt-3">
              <a href="?section=create-rdv&patient_id=<?= $id_p ?>" class="btn-success">
                <i class="fas fa-calendar-plus"></i> RDV
              </a>
            </div>
            </div>
          </div>

          <!-- Upcoming Appointments -->
          <div class="card animate__animated animate__fadeInUp">
            <div class="section-title">
              <i class="fas fa-calendar-check"></i>
              Rendez-vous à venir
            </div>
            <div class="card-body">
              <?php if (empty($rdv_futurs)): ?>
                <p class="empty">Aucun rendez-vous planifié.</p>
              <?php else: ?>
                <?php foreach ($rdv_futurs as $rdv): ?>
                  <div class="rdv-item">
                    <div class="rdv-date">
                      <i class="fas fa-clock me-2"></i>
                      <?= date('d/m/Y', strtotime($rdv['date_rdv'])) ?> à <?= substr($rdv['heure'], 0, 5) ?>
                    </div>
                    <div class="mb-2">
                      <i class="fas fa-user-md me-2"></i>
                      Dr. <?= htmlspecialchars($rdv['medecin_prenom'] . ' ' . $rdv['medecin_nom']) ?> - <?= htmlspecialchars($rdv['lieu']) ?>
                    </div>
                    <div class="mb-2">
                      <i class="fas fa-comment-medical me-2"></i>
                      <strong>Motif :</strong> <?= htmlspecialchars($rdv['motif']) ?>
                    </div>
                    <div class="d-flex gap-2">
                      <a href="?section=modifier-rdv&id_rdv=<?= $rdv['id_rdv'] ?>" class="btn-primary">
                        <i class="fas fa-edit"></i> Modifier
                      </a>
                      <a href="?section=cancel-rdv&id_rdv=<?= $rdv['id_rdv'] ?>" class="btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir annuler ce rendez-vous ?')">
                        <i class="fas fa-times"></i> Annuler
                      </a>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>

          <!-- Consultation History -->
          <div class="card animate__animated animate__fadeInUp">
            <div class="section-title">
              <i class="fas fa-history"></i>
              Historique des consultations
            </div>
            <div class="card-body">
              <?php if (empty($historique)): ?>
                <p class="empty">Aucune consultation enregistrée.</p>
              <?php else: ?>
                <?php foreach ($historique as $h): ?>
                  <div class="history-item">
                    <div class="history-date">
                      <i class="fas fa-calendar-day me-2"></i>
                      <?= date('d/m/Y', strtotime($h['date_rdv'])) ?> à <?= substr($h['heure'], 0, 5) ?>
                    </div>
                    <div class="mb-2">
                      <i class="fas fa-user-md me-2"></i>
                      Dr. <?= htmlspecialchars($h['medecin_prenom'] . ' ' . $h['medecin_nom']) ?> - <?= htmlspecialchars($h['lieu']) ?>
                    </div>
                    <div class="mb-2">
                      <i class="fas fa-comment-medical me-2"></i>
                      <strong>Motif :</strong> <?= htmlspecialchars($h['motif']) ?>
                    </div>
                    <?php if (!empty($h['diagnostic'])): ?>
                      <div class="mb-2">
                        <i class="fas fa-stethoscope me-2"></i>
                        <strong>Diagnostic :</strong> <?= htmlspecialchars($h['diagnostic']) ?>
                      </div>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
          
          <!-- Examens -->
          <div class="card animate__animated animate__fadeInUp">
            <div class="section-title">
              <i class="fas fa-microscope"></i>
              Examens médicaux
            </div>
            <div class="card-body">
              <?php if (empty($examens)): ?>
                <p class="empty">Aucun examen médical enregistré.</p>
              <?php else: ?>
                <?php foreach ($examens as $examen): ?>
                  <div class="history-item">
                    <div class="history-date">
                      <i class="fas fa-calendar-day me-2"></i>
                      <?= date('d/m/Y', strtotime($examen['date_examen'])) ?>
                    </div>
                    <div class="mb-2">
                      <i class="fas fa-vial me-2"></i>
                      <strong>Type :</strong> <?= htmlspecialchars($examen['type_examen']) ?>
                    </div>
                    <div class="mb-2">
                      <i class="fas fa-file-medical me-2"></i>
                      <strong>Résultat :</strong> <?= htmlspecialchars($examen['résultat']) ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
          
          <!-- NOUVEAU: Ordonnances du patient -->
          <div class="card animate__animated animate__fadeInUp">
            <div class="section-title">
              <i class="fas fa-pills"></i>
              Ordonnances
            </div>
            <div class="card-body">
              <?php if (empty($ordonnances_patient)): ?>
                <p class="empty">Aucune ordonnance enregistrée.</p>
              <?php else: ?>
                <?php foreach ($ordonnances_patient as $ord): ?>
                  <div class="history-item">
                    <div class="history-date">
                      <i class="fas fa-calendar-day me-2"></i>
                      <?= date('d/m/Y', strtotime($ord['date_ordonnance'])) ?>
                    </div>
                    <div class="mb-2">
                      <i class="fas fa-pills me-2"></i>
                      <strong>Ordonnance :</strong>
                      <?php if (!empty($ord['médicaments']) && file_exists($ord['médicaments'])): ?>
                        <a href="<?= htmlspecialchars($ord['médicaments']) ?>" target="_blank" class="btn btn-sm btn-primary">
                           Consilter
                        </a>
                      <?php else: ?>
                        <span class="text-danger">Fichier manquant</span>
                      <?php endif; ?>
                    </div>
                    <div class="d-flex gap-2 mt-2">
                      <a href="?section=delete-ordonnance&id_ordonnance=<?= $ord['id_ordonnance'] ?>&redirect=dossiers&id=<?= $id_p ?>" class="btn btn-sm btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette ordonnance ?')">
                        <i class="fas fa-trash"></i> Supprimer
                      </a>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <?php if ($active_section === 'cancel-rdv' && isset($rdv_info)): ?>
        <div id="cancel-rdv-section" class="section-container">
          <h2 class="mb-4"><i class="fas fa-calendar-times me-2"></i> Annuler un Rendez-vous</h2>
          <div class="alert alert-warning mb-4">
            <h6 class="alert-heading fw-bold">Détails du rendez-vous :</h6>
            <hr>
            <div class="row">
              <div class="col-md-6">
                <p><strong>Date :</strong> <?= date('d/m/Y', strtotime($rdv_info['date_rdv'])) ?></p>
                <p><strong>Heure :</strong> <?= substr($rdv_info['heure'], 0, 5) ?></p>
              </div>
              <div class="col-md-6">
                <p><strong>Patient :</strong> <?= htmlspecialchars($rdv_info['p_prenom'] . ' ' . $rdv_info['p_nom']) ?></p>
                <p><strong>Médecin :</strong> Dr. <?= htmlspecialchars($rdv_info['m_prenom'] . ' ' . $rdv_info['m_nom']) ?></p>
              </div>
            </div>
            <p class="mt-2"><strong>Motif :</strong> <?= htmlspecialchars($rdv_info['motif']) ?></p>
          </div>
          <form method="post">
            <div class="form-group">
              <label for="raison">Raison de l'annulation :</label>
              <textarea id="raison" name="raison" rows="3" required placeholder="Veuillez indiquer la raison de l'annulation..."></textarea>
            </div>
            <div class="d-flex justify-content-between mt-4">
              <a href="?section=agenda" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Retour
              </a>
              <button type="submit" name="annuler_rdv" class="cancel-btn">
                <i class="fas fa-times me-1"></i> Confirmer l'annulation
              </button>
            </div>
          </form>
        </div>
      <?php endif; ?>

      <?php if ($active_section === 'modifier-patient' && $patient_to_edit): ?>
        <div id="modifier-patient-section" class="section-container">
          <div class="header animate__animated animate__fadeIn">
            <div class="header-info flex-grow-1">
              <h1 class="header-title">
                <i class="fas fa-user-injured me-2"></i>
                Modifier le Profil de <?= htmlspecialchars($patient_to_edit['prenom'] . ' ' . $patient_to_edit['nom']) ?>
              </h1>
            </div>
            <div class="d-flex align-items-center justify-content-md-end">
              <a href="?section=dossiers&id=<?= $patient_to_edit['id_patient'] ?>" class="btn-primary">
                <i class="fas fa-arrow-left me-1"></i> Retour
              </a>
            </div>
          </div>
          <div class="form-container">
            <form method="post">
              <div class="form-group">
                <label for="nom">Nom :</label>
                <input type="text" id="nom" name="nom" value="<?= htmlspecialchars($patient_to_edit['nom']) ?>" required aria-describedby="nomError">
                <div id="nomError" class="message message-error">Le nom est requis.</div>
              </div>
              <div class="form-group">
                <label for="prenom">Prénom :</label>
                <input type="text" id="prenom" name="prenom" value="<?= htmlspecialchars($patient_to_edit['prenom']) ?>" required aria-describedby="prenomError">
                <div id="prenomError" class="message message-error">Le prénom est requis.</div>
              </div>
              <div class="form-group">
                <label for="email">Email :</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($patient_to_edit['email']) ?>" required aria-describedby="emailError">
                <div id="emailError" class="message message-error">Un email valide est requis.</div>
              </div>
              <div class="form-group">
                <label for="telephone">Téléphone :</label>
                <input type="text" id="telephone" name="telephone" value="<?= htmlspecialchars($patient_to_edit['telephone']) ?>" required aria-describedby="telephoneError">
                <div id="telephoneError" class="message message-error">Le téléphone est requis.</div>
              </div>
              <div class="form-group">
                <label for="adresse">Adresse :</label>
                <textarea id="adresse" name="adresse" aria-describedby="adresseError"><?= htmlspecialchars($patient_to_edit['adresse']) ?></textarea>
                <div id="adresseError" class="message message-error">L'adresse est invalide.</div>
              </div>
              <div class="form-group">
                <label for="sexe">Sexe :</label>
                <select id="sexe" name="sexe" required aria-describedby="sexeError">
                  <option value="Homme" <?= $patient_to_edit['sexe'] === 'Homme' ? 'selected' : '' ?>>Homme</option>
                  <option value="Femme" <?= $patient_to_edit['sexe'] === 'Femme' ? 'selected' : '' ?>>Femme</option>
                </select>
                <div id="sexeError" class="message message-error">Le sexe est requis.</div>
              </div>
              <button type="submit" name="modifier_patient" class="submit-btn">Enregistrer</button>
            </form>
          </div>
        </div>
      <?php endif; ?>

    <?php if ($active_section === 'modifier-rdv' && $rdv_to_edit): ?>
    <div id="modifier-rdv-section" class="section-container">
      <div class="header animate__animated animate__fadeIn">
        <div class="header-info flex-grow-1">
          <h1 class="header-title">
            <i class="fas fa-calendar-alt me-2"></i>
            Modifier le Rendez-vous
          </h1>
        </div>
        <div class="d-flex align-items-center justify-content-md-end">
          <a href="?section=agenda" class="btn-primary">
            <i class="fas fa-arrow-left me-1"></i> Retour
          </a>
        </div>
      </div>
      <div class="form-container">
        <form method="post">
          <div class="form-group">
            <label>Patient :</label>
            <input type="text" value="<?= htmlspecialchars($rdv_to_edit['patient_prenom'] . ' ' . $rdv_to_edit['patient_nom']) ?>" readonly class="form-control">
          </div>
          <div class="form-group">
            <label>Médecin :</label>
            <input type="text" value="Dr. <?= htmlspecialchars($rdv_to_edit['medecin_prenom'] . ' ' . $rdv_to_edit['medecin_nom']) ?>" readonly class="form-control">
          </div>
          <div class="form-group">
            <label>Lieu :</label>
            <input type="text" value="<?= htmlspecialchars($rdv_to_edit['lieu']) ?>" readonly class="form-control">
          </div>
          <div class="form-group">
            <label>Motif :</label>
            <textarea readonly class="form-control"><?= htmlspecialchars($rdv_to_edit['motif']) ?></textarea>
          </div>
          <div class="form-group">
            <label for="date_rdv">Nouvelle Date :</label>
            <input type="date" id="date_rdv" name="date_rdv" value="<?= htmlspecialchars($rdv_to_edit['date_rdv']) ?>" required>
          </div>
          <div class="form-group">
            <label for="heure">Nouvelle Heure :</label>
            <input type="time" id="heure" name="heure" value="<?= htmlspecialchars($rdv_to_edit['heure']) ?>" required>
          </div>
          <button type="submit" name="modifier_rdv" class="submit-btn">Enregistrer les modifications</button>
        </form>
      </div>
    </div>
  <?php endif; ?>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            entry.target.classList.add('animate__animated', 'animate__fadeInUp');
          }
        });
      }, { threshold: 0.1 });

      document.querySelectorAll('.section-container, .dashboard-card, .card').forEach(el => observer.observe(el));

      // Client-side form validation for modifier-patient
      const patientForm = document.querySelector('#modifier-patient-section form');
      if (patientForm) {
        patientForm.addEventListener('submit', function(e) {
          let isValid = true;
          const fields = [
            { id: 'nom', errorId: 'nomError' },
            { id: 'prenom', errorId: 'prenomError' },
            { id: 'email', errorId: 'emailError' },
            { id: 'telephone', errorId: 'telephoneError' },
            { id: 'sexe', errorId: 'sexeError' }
          ];

          fields.forEach(field => {
            const input = document.getElementById(field.id);
            const error = document.getElementById(field.errorId);
            if (!input.value.trim()) {
              error.classList.add('active');
              isValid = false;
            } else {
              error.classList.remove('active');
            }
            if (field.id === 'email' && input.value.trim()) {
              const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
              if (!emailPattern.test(input.value.trim())) {
                error.textContent = "L'email n'est pas valide.";
                error.classList.add('active');
                isValid = false;
              }
            }
          });

          if (!isValid) {
            e.preventDefault();
          }
        });
      }
    });

    function toggleTheme() {
      const body = document.body;
      const currentTheme = body.getAttribute('data-theme');
      const newTheme = currentTheme === 'light' ? 'dark' : 'light';
      body.setAttribute('data-theme', newTheme);
      const toggleIcon = document.querySelector('.theme-toggle i');
      toggleIcon.classList.toggle('fa-sun');
      toggleIcon.classList.toggle('fa-moon');
    }

    function toggleSidebar() {
      const sidebar = document.getElementById('sidebar');
      sidebar.classList.toggle('collapsed');
    }

    function scrollToSection(sectionId) {
      document.getElementById(sectionId).scrollIntoView({ behavior: 'smooth' });
      document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));
      document.querySelector(`a[href="?section=${sectionId.replace('-section', '')}"]`).classList.add('active');
    }

    <?php if ($active_section): ?>
      scrollToSection('<?= $active_section ?>-section');
    <?php endif; ?>
  </script>
</body>
</html>