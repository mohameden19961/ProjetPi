<?php

session_start();

// Vérification de l'authentification
if (!isset($_SESSION['user_id'])) {
    header("Location: connection.php");
    exit();
}

// Vérification du rôle administrateur
if ($_SESSION['role'] !== 'admin') {
    header("Location: acces_refuse.php");
    exit();
}

// Configuration de l'affichage des erreurs pour le développement
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Connexion à la base de données
$conn = new mysqli("localhost", "root", "", "gestion_cabinet_medical");
if ($conn->connect_error) {
    die("Erreur de connexion : " . $conn->connect_error);
}
$conn->set_charset("utf8mb4"); // Assurer la compatibilité avec les caractères spéciaux

// Fonction de sécurisation des entrées (utilisée pour les recherches principalement)
function secure_input($data)
{
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Fonction pour récupérer les statistiques
function get_stats($conn)
{
    $stats = [];
    $queries = [
        'patients' => "SELECT COUNT(*) as total FROM utilisateur WHERE rôle = 'patient'",
        'medecins' => "SELECT COUNT(*) as total FROM utilisateur WHERE rôle = 'medecin'",
        'assistants' => "SELECT COUNT(*) as total FROM utilisateur WHERE rôle = 'assistant'",
        'rdv_aujourdhui' => "SELECT COUNT(*) as total FROM rendezvous WHERE date_rdv = CURDATE()",
        'rdv_prochains' => "SELECT COUNT(*) as total FROM rendezvous WHERE date_rdv > CURDATE()",
        'hospitalises' => "SELECT COUNT(DISTINCT id_patient) as total FROM hospitalisation WHERE date_sortie IS NULL OR date_sortie >= CURDATE()",
        'ordonnances' => "SELECT COUNT(*) as total FROM ordonnance WHERE date_ordonnance >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)"
    ];

    foreach ($queries as $key => $query) {
        $result = $conn->query($query);
        $stats[$key] = $result ? $result->fetch_assoc()['total'] : 0;
    }
    $stats['patients_externes'] = $stats['patients'] - $stats['hospitalises'];
    return $stats;
}

// Initialisation des variables
$stats = get_stats($conn);
$all_users = [];
$userDetails = null;
$search = '';
$current_view = isset($_GET['view']) ? $_GET['view'] : 'dashboard';

// =================================================================
// GESTION DES ACTIONS (POST) - BLOC CORRIGÉ ET SÉCURISÉ
// =================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- GESTION DES AJOUTS ---
    // =================================================================
    // GESTION DES HOSPITALISATIONS (AJOUT / MODIFICATION)
    // =================================================================

    // --- AJOUT D'UNE NOUVELLE HOSPITALISATION ---
    // =================================================================
    // GESTION DES ACTIONS (POST) - BLOC CORRIGÉ ET SÉCURISÉ
    // =================================================================


    // --- GESTION DES HOSPITALISATIONS (AJOUT / MODIFICATION) ---

    // --- AJOUT D'UNE NOUVELLE HOSPITALISATION ---
    // --- AJOUT D'UNE NOUVELLE HOSPITALISATION ---


    // --- MODIFICATION D'UNE HOSPITALISATION EXISTANTE ---


    // ... (le reste de votre code POST pour les utilisateurs, rdv, etc.)



    // --- MODIFICATION D'UNE HOSPITALISATION EXISTANTE ---

    // =================================================================
    // GESTION DES HOSPITALISATIONS (AJOUT / MODIFICATION)
    // =================================================================

    // --- AJOUT D'UNE NOUVELLE HOSPITALISATION ---
    if (isset($_POST['add_hospitalization'])) {
        // 1. Récupérer et sécuriser les données du formulaire
        $patient_id = (int)$_POST['patient_id'];
        $medecin_id = (int)$_POST['medecin_id'];
        $date_entree = secure_input($_POST['date_entree']);
        $service = secure_input($_POST['service']);
        $diagnostic = "Hospitalisation en service " . $service; // Diagnostic initial

        // 2. Utiliser une transaction pour garantir l'intégrité des données
        $conn->begin_transaction();
        try {
            // 3. Créer un traitement associé à cette hospitalisation
            // C'est une bonne pratique pour lier l'hospitalisation à un médecin et un diagnostic
            $stmt_traitement = $conn->prepare("INSERT INTO traitement (id_patient, id_medecin, date_debut, diagnostic) VALUES (?, ?, ?, ?)");
            $stmt_traitement->bind_param("iiss", $patient_id, $medecin_id, $date_entree, $diagnostic);
            $stmt_traitement->execute();
            $traitement_id = $conn->insert_id; // Récupérer l'ID du traitement créé

            // 4. Insérer l'hospitalisation en la liant au traitement
            $stmt_hosp = $conn->prepare("INSERT INTO hospitalisation (id_traitement, id_patient, date_entree, service) VALUES (?, ?, ?, ?)");
            $stmt_hosp->bind_param("iiss", $traitement_id, $patient_id, $date_entree, $service);
            $stmt_hosp->execute();

            // 5. Valider la transaction si tout s'est bien passé
            $conn->commit();
            $_SESSION['message'] = "Hospitalisation ajoutée avec succès.";
            $_SESSION['message_type'] = "success";
        } catch (Exception $e) {
            // 6. Annuler la transaction en cas d'erreur
            $conn->rollback();
            $_SESSION['message'] = "Erreur lors de l'ajout de l'hospitalisation : " . $e->getMessage();
            $_SESSION['message_type'] = "error";
        }

        // 7. Rediriger vers la vue des hospitalisés
        header("Location: dashboard_administrateur.php?view=hospitalized");
        exit();
    }

    // --- MODIFICATION D'UNE HOSPITALISATION EXISTANTE (EX: AJOUT DATE DE SORTIE) ---
    // --- MODIFICATION D'UNE HOSPITALISATION EXISTANTE (EX: AJOUT DATE DE SORTIE) ---
    if (isset($_POST['edit_hospitalization'])) {
        // 1. Récupérer et sécuriser les données
        $hosp_id = (int)$_POST['hospitalisation_id'];
        $service = secure_input($_POST['service']);

        // CORRECTION : On vérifie explicitement si le champ est vide.
        // Si le champ 'date_sortie' est soumis mais vide, on le force à NULL.
        // S'il n'est pas soumis du tout, il sera aussi NULL.
        $date_sortie = null;
        if (isset($_POST['date_sortie']) && !empty($_POST['date_sortie'])) {
            $date_sortie = secure_input($_POST['date_sortie']);
        }

        try {
            // 2. Préparer la requête de mise à jour
            $stmt = $conn->prepare("UPDATE hospitalisation SET date_sortie = ?, service = ? WHERE id_hospitalisation = ?");
            // Le bind_param gère correctement la conversion de la variable PHP `null` en `NULL` SQL.
            $stmt->bind_param("ssi", $date_sortie, $service, $hosp_id);

            if ($stmt->execute()) {
                $_SESSION['message'] = "Hospitalisation modifiée avec succès.";
                $_SESSION['message_type'] = "success";
            } else {
                throw new Exception($conn->error);
            }
        } catch (Exception $e) {
            $_SESSION['message'] = "Erreur lors de la modification : " . $e->getMessage();
            $_SESSION['message_type'] = "error";
        }

        // 3. Rediriger
        header("Location: dashboard_administrateur.php?view=hospitalized");
        exit();
    }

    // --- GESTION DE LA SORTIE RAPIDE D'UN PATIENT ---
    if (isset($_POST['discharge_patient'])) {
        $hosp_id = (int)$_POST['hospitalisation_id'];
        $date_sortie = date('Y-m-d'); // La date de sortie est aujourd'hui

        try {
            $stmt = $conn->prepare("UPDATE hospitalisation SET date_sortie = ? WHERE id_hospitalisation = ?");
            $stmt->bind_param("si", $date_sortie, $hosp_id);
            if ($stmt->execute()) {
                $_SESSION['message'] = "La sortie du patient a été enregistrée.";
                $_SESSION['message_type'] = "success";
            } else {
                throw new Exception($conn->error);
            }
        } catch (Exception $e) {
            $_SESSION['message'] = "Erreur lors de l'enregistrement de la sortie : " . $e->getMessage();
            $_SESSION['message_type'] = "error";
        }

        header("Location: dashboard_administrateur.php?view=hospitalized");
        exit();
    }


    if (isset($_POST['add_user'])) {
        $nom = secure_input($_POST['nom']);
        $prenom = secure_input($_POST['prenom']);
        $email = secure_input($_POST['email']);
        $telephone = secure_input($_POST['telephone']);
        $role = secure_input($_POST['role']);
        $specialite = ($role == 'medecin' && isset($_POST['specialite'])) ? secure_input($_POST['specialite']) : null;

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO utilisateur (nom, prenom, email, telephone, rôle) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $nom, $prenom, $email, $telephone, $role);
            $stmt->execute();
            $user_id = $conn->insert_id;

            if ($role == 'medecin') {
                $stmt_medecin = $conn->prepare("INSERT INTO medecin (id_medecin, spécialité) VALUES (?, ?)");
                $stmt_medecin->bind_param("is", $user_id, $specialite);
                $stmt_medecin->execute();
            }

            $default_password = password_hash('123456', PASSWORD_DEFAULT);
            $stmt_connexion = $conn->prepare("INSERT INTO connexion (id_utilisateur, login, mot_de_passe) VALUES (?, ?, ?)");
            $stmt_connexion->bind_param("iss", $user_id, $email, $default_password);
            $stmt_connexion->execute();

            $conn->commit();
            $_SESSION['message'] = "Utilisateur ajouté avec succès. Mot de passe par défaut: 123456";
            $_SESSION['message_type'] = "success";
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['message'] = "Erreur lors de l'ajout de l'utilisateur: " . $e->getMessage();
            $_SESSION['message_type'] = "error";
        }
        header("Location: dashboard_administrateur.php?view=users");
        exit();
    }

    if (isset($_POST['add_patient'])) {
        $nom = secure_input($_POST['nom']);
        $prenom = secure_input($_POST['prenom']);
        $email = secure_input($_POST['email']);
        $telephone = secure_input($_POST['telephone']);
        $date_naissance = secure_input($_POST['date_naissance']);
        $sexe = secure_input($_POST['sexe']);
        $adresse = secure_input($_POST['adresse']);
        $role_patient = 'patient';

        $conn->begin_transaction();
        try {
            $stmt_user = $conn->prepare("INSERT INTO utilisateur (nom, prenom, email, telephone, rôle) VALUES (?, ?, ?, ?, ?)");
            $stmt_user->bind_param("sssss", $nom, $prenom, $email, $telephone, $role_patient);
            $stmt_user->execute();
            $patient_user_id = $conn->insert_id;

            $stmt_patient = $conn->prepare("INSERT INTO patient (id_patient, date_naissance, sexe, adresse) VALUES (?, ?, ?, ?)");
            $stmt_patient->bind_param("isss", $patient_user_id, $date_naissance, $sexe, $adresse);
            $stmt_patient->execute();

            $conn->commit();
            $_SESSION['message'] = "Patient ajouté avec succès.";
            $_SESSION['message_type'] = "success";
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['message'] = "Erreur lors de l'ajout du patient: " . $e->getMessage();
            $_SESSION['message_type'] = "error";
        }
        header("Location: dashboard_administrateur.php?view=patients");
        exit();
    }

    // --- GESTION DES MODIFICATIONS ---

    // --- GESTION DES MODIFICATIONS ---

    // =================================================================
    // GESTION DES MODIFICATIONS UTILISATEUR (SOLUTION FINALE)
    // =================================================================
    // =================================================================
    // GESTION DES MODIFICATIONS UTILISATEUR (SOLUTION FINALE)
    // =================================================================
    // DANS VOTRE BLOC : if ($_SERVER['REQUEST_METHOD'] === 'POST') { ... }

    // =================================================================
    // GESTION DES MODIFICATIONS UTILISATEUR (SOLUTION FINALE)
    // =================================================================
    if (isset($_POST['edit_user'])) {
        $user_id = (int)$_POST['user_id'];
        $nom = secure_input($_POST['nom']);
        $prenom = secure_input($_POST['prenom']);
        $email = secure_input($_POST['email']);
        $telephone = secure_input($_POST['telephone']);
        $role = secure_input($_POST['role']);

        $conn->begin_transaction();
        try {
            // 1. Mettre à jour les informations de base dans la table 'utilisateur'
            $stmt_user = $conn->prepare("UPDATE utilisateur SET nom = ?, prenom = ?, email = ?, telephone = ?, rôle = ? WHERE id_utilisateur = ?");
            $stmt_user->bind_param("sssssi", $nom, $prenom, $email, $telephone, $role, $user_id);
            $stmt_user->execute();

            // 2. Gérer les informations spécifiques au rôle (Patient ou Médecin)
            if ($role == 'patient') {
                $date_naissance = secure_input($_POST['date_naissance']);
                $sexe = secure_input($_POST['sexe']);
                $adresse = secure_input($_POST['adresse']);

                // Vérifier si une entrée patient existe déjà pour décider entre INSERT et UPDATE
                $check_stmt = $conn->prepare("SELECT id_patient FROM patient WHERE id_patient = ?");
                $check_stmt->bind_param("i", $user_id);
                $check_stmt->execute();
                $result = $check_stmt->get_result();

                if ($result->num_rows > 0) {
                    // Le patient existe -> on met à jour ses détails
                    $stmt_patient = $conn->prepare("UPDATE patient SET date_naissance = ?, sexe = ?, adresse = ? WHERE id_patient = ?");
                    $stmt_patient->bind_param("sssi", $date_naissance, $sexe, $adresse, $user_id);
                } else {
                    // Le patient n'existe pas (ex: un assistant devient patient) -> on insère ses détails
                    $stmt_patient = $conn->prepare("INSERT INTO patient (id_patient, date_naissance, sexe, adresse) VALUES (?, ?, ?, ?)");
                    $stmt_patient->bind_param("isss", $user_id, $date_naissance, $sexe, $adresse);
                }
                $stmt_patient->execute();
            } elseif ($role == 'medecin') {
                $specialite = secure_input($_POST['specialite']);
                // Logique similaire pour le médecin (UPDATE ou INSERT)
                $check_stmt = $conn->prepare("SELECT id_medecin FROM medecin WHERE id_medecin = ?");
                $check_stmt->bind_param("i", $user_id);
                $check_stmt->execute();
                $result = $check_stmt->get_result();

                if ($result->num_rows > 0) {
                    $stmt_medecin = $conn->prepare("UPDATE medecin SET spécialité = ? WHERE id_medecin = ?");
                    $stmt_medecin->bind_param("si", $specialite, $user_id);
                } else {
                    $stmt_medecin = $conn->prepare("INSERT INTO medecin (id_medecin, spécialité) VALUES (?, ?)");
                    $stmt_medecin->bind_param("is", $user_id, $specialite);
                }
                $stmt_medecin->execute();
            }

            // 3. Valider la transaction et définir le message de SUCCÈS
            $conn->commit();
            $_SESSION['message'] = "Les informations ont été modifiées avec succès.";
            $_SESSION['message_type'] = "success";
        } catch (Exception $e) {
            // En cas d'erreur, annuler toutes les modifications et définir le message d'ERREUR
            $conn->rollback();
            $_SESSION['message'] = "Erreur lors de la modification : " . $e->getMessage();
            $_SESSION['message_type'] = "error";
        }

        // 4. Rediriger vers la vue appropriée pour afficher le message
        $redirect_view = ($role == 'patient') ? 'patients' : 'users';
        header("Location: dashboard_administrateur.php?view=" . $redirect_view);
        exit(); // Ne jamais oublier exit() après un header de redirection
    }



    // =================================================================
    // GESTION DE LA MISE À JOUR DU PROFIL ADMINISTRATEUR (SOLUTION FINALE)
    // =================================================================
    if (isset($_POST['update_profile'])) {
        $user_id = (int)$_SESSION['user_id'];
        $nom = secure_input($_POST['nom']);
        $prenom = secure_input($_POST['prenom']);
        $email = secure_input($_POST['email']);
        $telephone = secure_input($_POST['telephone']);

        // On commence une transaction pour s'assurer que tout réussit ou tout échoue
        $conn->begin_transaction();
        try {
            // 1. Mettre à jour les informations textuelles
            $stmt = $conn->prepare("UPDATE utilisateur SET nom = ?, prenom = ?, email = ?, telephone = ? WHERE id_utilisateur = ?");
            $stmt->bind_param("ssssi", $nom, $prenom, $email, $telephone, $user_id);
            $stmt->execute();

            // 2. Gérer la photo de profil SI un nouveau fichier est envoyé
            if (!empty($_FILES['profile_picture']['name'])) {
                $target_dir = "uploads/profiles/";
                if (!file_exists($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }

                $file_extension = pathinfo($_FILES["profile_picture"]["name"], PATHINFO_EXTENSION);
                // Créer un nom de fichier unique pour éviter les conflits
                $new_filename = 'profile_' . $user_id . '_' . time() . '.' . $file_extension;
                $target_file = $target_dir . $new_filename;

                // Déplacer le fichier téléchargé
                if (move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $target_file)) {
                    // Mettre à jour le chemin de la photo dans la base de données
                    $stmt_photo = $conn->prepare("UPDATE utilisateur SET photo_profil = ? WHERE id_utilisateur = ?");
                    $stmt_photo->bind_param("si", $target_file, $user_id);
                    $stmt_photo->execute();

                    // Mettre à jour la session avec la NOUVELLE photo
                    $_SESSION['profile_picture'] = $target_file;
                } else {
                    // Si le téléchargement échoue, on annule tout
                    throw new Exception("Erreur lors du téléchargement de l'image.");
                }
            }

            // 3. Mettre à jour les informations de la session pour un affichage immédiat
            $_SESSION['ad_name'] = $prenom . ' ' . $nom;
            $_SESSION['email'] = $email;
            $_SESSION['telephone'] = $telephone;
            // La photo est déjà mise à jour dans la condition ci-dessus

            // Si tout s'est bien passé, on valide les changements
            $conn->commit();
            $_SESSION['message'] = "Profil mis à jour avec succès.";
            $_SESSION['message_type'] = "success";
        } catch (Exception $e) {
            // S'il y a eu une erreur, on annule toutes les requêtes
            $conn->rollback();
            $_SESSION['message'] = "Erreur lors de la mise à jour du profil : " . $e->getMessage();
            $_SESSION['message_type'] = "error";
        }

        // Rediriger pour rafraîchir la page et afficher le message
        header("Location: dashboard_administrateur.php");
        exit();
    }

    // =================================================================
    // GESTION DES RENDEZ-VOUS (AJOUT / MODIFICATION)
    // =================================================================

    // --- AJOUT D'UN NOUVEAU RENDEZ-VOUS ---
    if (isset($_POST['add_appointment'])) {
        $patient_id = (int)$_POST['patient_id'];
        $medecin_id = (int)$_POST['medecin_id'];
        $date_rdv = secure_input($_POST['date_rdv']);
        $heure = secure_input($_POST['heure']);
        $motif = secure_input($_POST['motif']);
        $lieu = secure_input($_POST['lieu']);
        $statut = 'confirmé'; // Statut par défaut à la création

        $conn->begin_transaction();
        try {
            // 1. Créer un traitement associé (si nécessaire)
            // On suppose qu'un RDV est lié à un traitement.
            // Si un traitement existe déjà pour ce patient avec ce médecin, on pourrait le réutiliser.
            // Pour simplifier, on en crée un nouveau.
            $stmt_traitement = $conn->prepare("INSERT INTO traitement (id_patient, id_medecin, date_debut, diagnostic) VALUES (?, ?, ?, ?)");
            $diagnostic_initial = "Consultation pour : " . $motif;
            $stmt_traitement->bind_param("iiss", $patient_id, $medecin_id, $date_rdv, $diagnostic_initial);
            $stmt_traitement->execute();
            $traitement_id = $conn->insert_id;

            // 2. Insérer le rendez-vous
            $stmt_rdv = $conn->prepare("INSERT INTO rendezvous (id_traitement, date_rdv, heure, motif, lieu, statut) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt_rdv->bind_param("isssss", $traitement_id, $date_rdv, $heure, $motif, $lieu, $statut);
            $stmt_rdv->execute();

            $conn->commit();
            $_SESSION['message'] = "Le rendez-vous a été ajouté avec succès.";
            $_SESSION['message_type'] = "success";
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['message'] = "Erreur lors de l'ajout du rendez-vous : " . $e->getMessage();
            $_SESSION['message_type'] = "error";
        }

        header("Location: dashboard_administrateur.php?view=appointments");
        exit();
    }

    // --- MODIFICATION D'UN RENDEZ-VOUS EXISTANT ---
    if (isset($_POST['edit_appointment'])) {
        $rdv_id = (int)$_POST['rdv_id'];
        $date_rdv = secure_input($_POST['date_rdv']);
        $heure = secure_input($_POST['heure']);
        $motif = secure_input($_POST['motif']);
        $lieu = secure_input($_POST['lieu']);
        $statut = secure_input($_POST['statut']);

        try {
            $stmt = $conn->prepare("UPDATE rendezvous SET date_rdv = ?, heure = ?, motif = ?, lieu = ?, statut = ? WHERE id_rdv = ?");
            $stmt->bind_param("sssssi", $date_rdv, $heure, $motif, $lieu, $statut, $rdv_id);

            if ($stmt->execute()) {
                $_SESSION['message'] = "Le rendez-vous a été modifié avec succès.";
                $_SESSION['message_type'] = "success";
            } else {
                throw new Exception($conn->error);
            }
        } catch (Exception $e) {
            $_SESSION['message'] = "Erreur lors de la modification du rendez-vous : " . $e->getMessage();
            $_SESSION['message_type'] = "error";
        }

        header("Location: dashboard_administrateur.php?view=appointments");
        exit();
    }

    // ... (Ajoutez ici les autres traitements POST comme add_appointment, change_password, etc., en suivant le même modèle sécurisé)
}

// =================================================================
// GESTION DE LA SUPPRESSION (GET)
// =================================================================
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    $redirect_view = 'users';

    $user_role_query = $conn->query("SELECT rôle FROM utilisateur WHERE id_utilisateur = $delete_id");
    if ($user_role_query->num_rows > 0) {
        $user_role = $user_role_query->fetch_assoc()['rôle'];
        if ($user_role === 'patient') {
            $redirect_view = 'patients';
        }

        $conn->begin_transaction();
        try {
            // Logique de suppression en cascade manuelle
            if ($user_role === 'patient') {
                $conn->query("DELETE FROM rendezvous WHERE id_traitement IN (SELECT id_traitement FROM traitement WHERE id_patient = $delete_id)");
                $conn->query("DELETE FROM ordonnance WHERE id_traitement IN (SELECT id_traitement FROM traitement WHERE id_patient = $delete_id)");
                $conn->query("DELETE FROM hospitalisation WHERE id_patient = $delete_id");
                $conn->query("DELETE FROM traitement WHERE id_patient = $delete_id");
                $conn->query("DELETE FROM patient WHERE id_patient = $delete_id");
            } elseif ($user_role === 'medecin') {
                $conn->query("DELETE FROM medecin WHERE id_medecin = $delete_id");
            }

            // Suppression de l'utilisateur et de sa connexion
            $conn->query("DELETE FROM connexion WHERE id_utilisateur = $delete_id");
            $conn->query("DELETE FROM utilisateur WHERE id_utilisateur = $delete_id");

            $conn->commit();
            $_SESSION['message'] = "Utilisateur supprimé avec succès";
            $_SESSION['message_type'] = "success";
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['message'] = "Erreur lors de la suppression: " . $e->getMessage();
            $_SESSION['message_type'] = "error";
        }
    }
    header("Location: dashboard_administrateur.php?view=" . $redirect_view);
    exit();
}

// =================================================================
// GESTION DE LA RÉCUPÉRATION DES DONNÉES POUR AFFICHAGE
// =================================================================

// Récupération des détails d'un utilisateur pour la vue 'user_details'
if (isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
    $userId = (int)$_GET['user_id'];
    $current_view = 'user_details';

    $result = $conn->query("SELECT * FROM utilisateur WHERE id_utilisateur = $userId");
    if ($result->num_rows > 0) {
        $userInfo = $result->fetch_assoc();
        $role = strtolower($userInfo['rôle']);
        $userDetails = ['info' => $userInfo, 'type' => $role, 'details' => []];

        if ($role === 'patient') {
            $resultPatient = $conn->query("SELECT * FROM patient WHERE id_patient = $userId");
            if ($resultPatient->num_rows > 0) $userDetails['details'] = $resultPatient->fetch_assoc();
        } elseif ($role === 'medecin') {
            $resultMedecin = $conn->query("SELECT * FROM medecin WHERE id_medecin = $userId");
            if ($resultMedecin->num_rows > 0) $userDetails['details'] = $resultMedecin->fetch_assoc();
        }
    }
}

// Gestion de la recherche et de l'affichage pour les vues 'users' et 'patients'
if ($current_view == 'users' || $current_view == 'patients') {

    // On joint les tables nécessaires pour avoir toutes les infos
    $base_query = "
        SELECT 
            u.id_utilisateur AS id, u.nom, u.prenom, u.email, u.telephone, u.rôle,
            p.date_naissance, p.sexe, p.adresse,
            m.spécialité,
            -- DÉBUT DE LA MODIFICATION : Ajout du statut d'hospitalisation
            -- On vérifie s'il existe une entrée dans la table hospitalisation
            -- où le patient est actuellement hospitalisé (date_sortie est NULL ou dans le futur)
            CASE 
                WHEN h.id_hospitalisation IS NOT NULL THEN 'Hospitalisé'
                ELSE 'Externe'
            END AS statut_hospitalisation
            -- FIN DE LA MODIFICATION
        FROM utilisateur u
        LEFT JOIN patient p ON u.id_utilisateur = p.id_patient
        LEFT JOIN medecin m ON u.id_utilisateur = m.id_medecin
        -- DÉBUT DE LA MODIFICATION : Jointure avec la table hospitalisation
        LEFT JOIN hospitalisation h ON u.id_utilisateur = h.id_patient AND (h.date_sortie IS NULL OR h.date_sortie >= CURDATE())
        -- FIN DE LA MODIFICATION
    ";

    $where_clauses = [];

    if ($current_view == 'patients') {
        $where_clauses[] = "u.rôle = 'patient'";
    } else {
        // Pour la vue 'users', on exclut les patients
        $where_clauses[] = "u.rôle != 'patient'";
    }

    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $search = secure_input($_GET['search']);
        $search_term = "%" . $conn->real_escape_string($search) . "%";
        $where_clauses[] = "(u.nom LIKE '$search_term' OR u.prenom LIKE '$search_term' OR u.email LIKE '$search_term')";
    }

    if (isset($_GET['departement']) && $_GET['departement'] != 'tous' && $current_view == 'users') {
        $departement = $conn->real_escape_string($_GET['departement']);
        $where_clauses[] = "u.rôle = '$departement'";
    }

    $sql = $base_query . " WHERE " . implode(' AND ', $where_clauses) . " ORDER BY u.nom, u.prenom";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $all_users[] = $row;
        }
    }
}

// Récupération des listes de patients et médecins pour les modales
$patients_list = [];
$medecins_list = [];
$patients_result = $conn->query("SELECT u.id_utilisateur, u.nom, u.prenom FROM utilisateur u WHERE u.rôle = 'patient' ORDER BY u.nom, u.prenom");
if ($patients_result) {
    while ($row = $patients_result->fetch_assoc()) $patients_list[] = $row;
}
$medecins_result = $conn->query("SELECT u.id_utilisateur, u.nom, u.prenom, m.spécialité FROM utilisateur u JOIN medecin m ON u.id_utilisateur = m.id_medecin WHERE u.rôle = 'medecin' ORDER BY u.nom, u.prenom");
if ($medecins_result) {
    while ($row = $medecins_result->fetch_assoc()) $medecins_list[] = $row;
}

// ... (Les autres blocs de récupération de données pour 'appointments', 'prescriptions', etc. restent ici)
// Assurez-vous qu'ils utilisent également des requêtes sécurisées si des entrées utilisateur sont impliquées.



// Gestion des rendez-vous
if ($current_view == 'appointments') {
    $where = "WHERE r.date_rdv >= CURDATE()";

    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $search = secure_input($_GET['search']);
        $where .= " AND (up.nom LIKE '%$search%' OR up.prenom LIKE '%$search%' OR um.nom LIKE '%$search%' OR um.prenom LIKE '%$search%')";
    }

    $sql = "SELECT r.id_rdv, r.date_rdv, r.heure, r.lieu, r.motif, r.statut, 
                   up.nom AS patient_nom, up.prenom AS patient_prenom,
                   um.nom AS medecin_nom, um.prenom AS medecin_prenom
            FROM rendezvous r
            JOIN traitement t ON r.id_traitement = t.id_traitement
            JOIN utilisateur up ON t.id_patient = up.id_utilisateur
            JOIN utilisateur um ON t.id_medecin = um.id_utilisateur
            $where
            ORDER BY r.date_rdv, r.heure";
    $result = $conn->query($sql);
    $appointments = [];
    while ($row = $result->fetch_assoc()) {
        $appointments[] = $row;
    }
}

// Gestion des ordonnances
if ($current_view == 'prescriptions') {
    $where = "";

    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $search = secure_input($_GET['search']);
        $where .= " AND (up.nom LIKE '%$search%' OR up.prenom LIKE '%$search%' OR um.nom LIKE '%$search%' OR um.prenom LIKE '%$search%')";
    }

    $sql = "SELECT o.id_ordonnance, o.date_ordonnance, o.médicaments,
                   up.nom AS patient_nom, up.prenom AS patient_prenom,
                   um.nom AS medecin_nom, um.prenom AS medecin_prenom
            FROM ordonnance o
            LEFT JOIN traitement t ON o.id_traitement = t.id_traitement
            LEFT JOIN utilisateur up ON t.id_patient = up.id_utilisateur
            LEFT JOIN utilisateur um ON t.id_medecin = um.id_utilisateur
            $where
            ORDER BY o.date_ordonnance DESC
            LIMIT 50";


    $result = $conn->query($sql);
    $prescriptions = [];
    while ($row = $result->fetch_assoc()) {
        $prescriptions[] = $row;
    }
}

// Gestion des patients hospitalisés
// Gestion des patients hospitalisés
if ($current_view == 'hospitalized') {
    $hospitalized = [];
    $sql = "SELECT 
                h.id_hospitalisation, h.date_entree, h.date_sortie, h.service,
                up.id_utilisateur AS id_patient, up.nom AS patient_nom, up.prenom AS patient_prenom,
                um.id_utilisateur AS id_medecin, um.nom AS medecin_nom, um.prenom AS medecin_prenom
            FROM hospitalisation h
            JOIN utilisateur up ON h.id_patient = up.id_utilisateur
            JOIN traitement t ON h.id_traitement = t.id_traitement
            JOIN utilisateur um ON t.id_medecin = um.id_utilisateur
            -- VERSION FINALE ET CORRECTE :
            -- Un patient est hospitalisé si sa date de sortie est NULL
            -- OU si sa date de sortie est dans le futur (cas d'une sortie planifiée).
            WHERE (h.date_sortie IS NULL OR h.date_sortie >= CURDATE())";

    if (!empty($search)) {
        $sql .= " AND (up.nom LIKE ? OR up.prenom LIKE ?)";
        $stmt = $conn->prepare($sql);
        $search_term = "%" . $search . "%";
        $stmt->bind_param("ss", $search_term, $search_term);
    } else {
        $stmt = $conn->prepare($sql);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $hospitalized[] = $row;
        }
    }
}


// =================================================================
// GESTION DES ACTIONS (POST) - BLOC CORRIGÉ ET SÉCURISÉ
// =================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ... (votre code existant pour add_user, edit_user, etc.)

    // --- GESTION DE L'ANNULATION D'UN RENDEZ-VOUS ---
    if (isset($_POST['cancel_appointment'])) {
        $rdv_id = (int)$_POST['rdv_id'];

        // On met à jour le statut du rendez-vous à 'annulé'
        // C'est mieux que de le supprimer pour garder un historique.
        try {
            $stmt = $conn->prepare("UPDATE rendezvous SET statut = 'annulé' WHERE id_rdv = ?");
            $stmt->bind_param("i", $rdv_id);

            if ($stmt->execute()) {
                $_SESSION['message'] = "Le rendez-vous a été annulé avec succès.";
                $_SESSION['message_type'] = "success";
            } else {
                throw new Exception($conn->error);
            }
        } catch (Exception $e) {
            $_SESSION['message'] = "Erreur lors de l'annulation du rendez-vous : " . $e->getMessage();
            $_SESSION['message_type'] = "error";
        }

        // Redirection vers la page des rendez-vous pour voir le changement
        header("Location: dashboard_administrateur.php?view=appointments");
        exit();
    }


    // ... (le reste de votre code POST)
}

// Changement de mot de passe
if (isset($_POST['change_password'])) {
    $current_password = secure_input($_POST['current_password']);
    $new_password = secure_input($_POST['new_password']);
    $confirm_password = secure_input($_POST['confirm_password']);

    $user_id = $_SESSION['user_id'];
    $sql = "SELECT mot_de_passe FROM connexion WHERE id_utilisateur = $user_id";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stored_password = $row['mot_de_passe'];

        if (password_verify($current_password, $stored_password)) {
            if ($new_password === $confirm_password) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $conn->query("UPDATE connexion SET mot_de_passe = '$hashed_password' WHERE id_utilisateur = $user_id");

                $_SESSION['message'] = "Mot de passe mis à jour avec succès";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "Les nouveaux mots de passe ne correspondent pas";
                $_SESSION['message_type'] = "error";
            }
        } else {
            $_SESSION['message'] = "Mot de passe actuel incorrect";
            $_SESSION['message_type'] = "error";
        }
    }
    header("Location: dashboard_administrateur.php");
    exit();
}

// Mise à jour du profil
// =================================================================
// GESTION DE LA MISE À JOUR DU PROFIL ADMINISTRATEUR (AVEC RÉPONSE JSON)
// =================================================================
// Dans votre bloc if ($_SERVER['REQUEST_METHOD'] === 'POST') { ... }

// =================================================================
// GESTION DE LA MISE À JOUR DU PROFIL ADMINISTRATEUR (SOLUTION FINALE)
// =================================================================
if (isset($_POST['update_profile'])) {
    $user_id = (int)$_SESSION['user_id'];
    $nom = secure_input($_POST['nom']);
    $prenom = secure_input($_POST['prenom']);
    $email = secure_input($_POST['email']);
    $telephone = secure_input($_POST['telephone']);

    // On commence une transaction pour s'assurer que tout réussit ou tout échoue
    $conn->begin_transaction();
    try {
        // 1. Mettre à jour les informations textuelles
        $stmt = $conn->prepare("UPDATE utilisateur SET nom = ?, prenom = ?, email = ?, telephone = ? WHERE id_utilisateur = ?");
        $stmt->bind_param("ssssi", $nom, $prenom, $email, $telephone, $user_id);
        $stmt->execute();

        // 2. Gérer la photo de profil SI un nouveau fichier est envoyé
        if (!empty($_FILES['profile_picture']['name'])) {
            $target_dir = "uploads/profiles/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }

            $file_extension = pathinfo($_FILES["profile_picture"]["name"], PATHINFO_EXTENSION);
            // Créer un nom de fichier unique pour éviter les conflits
            $new_filename = 'profile_' . $user_id . '_' . time() . '.' . $file_extension;
            $target_file = $target_dir . $new_filename;

            // Déplacer le fichier téléchargé
            if (move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $target_file)) {
                // Mettre à jour le chemin de la photo dans la base de données
                $stmt_photo = $conn->prepare("UPDATE utilisateur SET photo_profil = ? WHERE id_utilisateur = ?");
                $stmt_photo->bind_param("si", $target_file, $user_id);
                $stmt_photo->execute();
            } else {
                // Si le téléchargement échoue, on annule tout
                throw new Exception("Erreur lors du téléchargement de l'image.");
            }
        }

        // Si tout s'est bien passé, on valide les changements
        $conn->commit();
        $_SESSION['message'] = "Profil mis à jour avec succès.";
        $_SESSION['message_type'] = "success";
    } catch (Exception $e) {
        // S'il y a eu une erreur, on annule toutes les requêtes
        $conn->rollback();
        $_SESSION['message'] = "Erreur lors de la mise à jour du profil : " . $e->getMessage();
        $_SESSION['message_type'] = "error";
    }

    // Rediriger pour rafraîchir la page et afficher le message.
    // La récupération des données au début du script se chargera de mettre à jour la session.
    header("Location: dashboard_administrateur.php?view=dashboard"); // Redirige vers le dashboard
    exit();
}


// ... après la connexion à la base de données ($conn = new mysqli(...))

// --- DÉBUT DE LA CORRECTION : RÉCUPÉRATION DES DONNÉES ADMIN ---
// On récupère les informations à jour de l'admin depuis la BDD à chaque chargement
if (isset($_SESSION['user_id'])) {
    $admin_id = (int)$_SESSION['user_id'];
    $admin_query = $conn->prepare("SELECT nom, prenom, email, telephone, photo_profil FROM utilisateur WHERE id_utilisateur = ?");
    $admin_query->bind_param("i", $admin_id);
    $admin_query->execute();
    $admin_result = $admin_query->get_result();

    if ($admin_result->num_rows > 0) {
        $admin_data = $admin_result->fetch_assoc();
        // On met à jour la session avec les données fraîches de la BDD
        $_SESSION['ad_name'] = $admin_data['prenom'] . ' ' . $admin_data['nom'];
        $_SESSION['email'] = $admin_data['email'];
        $_SESSION['telephone'] = $admin_data['telephone'];
        $_SESSION['profile_picture'] = $admin_data['photo_profil'] ?? 'img.jpg'; // Utilise une image par défaut si null
    }
}
// --- FIN DE LA CORRECTION ---

// Le reste de votre code (fonction secure_input, get_stats, etc.)
// ...


$patients_list = [];
$medecins_list = [];

// Requête pour récupérer tous les utilisateurs qui sont des patients
$patients_query = "SELECT u.id_utilisateur, u.nom, u.prenom 
                   FROM utilisateur u 
                   JOIN patient p ON u.id_utilisateur = p.id_patient 
                   WHERE u.rôle = 'patient'
                   ORDER BY u.nom, u.prenom";

$patients_result = $conn->query($patients_query);
if ($patients_result) {
    while ($row = $patients_result->fetch_assoc()) {
        $patients_list[] = $row;
    }
}

// Requête pour récupérer tous les utilisateurs qui sont des médecins
$medecins_query = "SELECT u.id_utilisateur, u.nom, u.prenom 
                   FROM utilisateur u 
                   JOIN medecin m ON u.id_utilisateur = m.id_medecin 
                   WHERE u.rôle = 'medecin'
                   ORDER BY u.nom, u.prenom";

$medecins_result = $conn->query($medecins_query);
if ($medecins_result) {
    while ($row = $medecins_result->fetch_assoc()) {
        $medecins_list[] = $row;
    }
}

?>
<?php
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord Administrateur</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2ecc71;
            --accent-color: #e74c3c;
            --light-color: #f8f9fa;
            --dark-color: #2c3e50;
            --gray-color: #95a5a6;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --info-color: #3498db;
            --border-radius: 8px;
            --box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }

        .admin-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 280px;
            background: linear-gradient(135deg, var(--dark-color), #34495e);
            color: white;
            padding: 1.5rem 0;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 0 1.5rem 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .logo {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }

        .logo i {
            font-size: 1.8rem;
            margin-right: 0.8rem;
            color: var(--primary-color);
        }

        .logo h1 {
            font-size: 1.3rem;
            font-weight: 600;
        }

        .profile-info {
            display: flex;
            align-items: center;
            margin-top: 1.5rem;
            padding: 0.8rem;
            border-radius: var(--border-radius);
            transition: var(--transition);
            cursor: pointer;
        }

        .profile-info:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .profile-pic {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 0.8rem;
            border: 2px solid var(--primary-color);
        }

        .profile-name {
            font-size: 0.9rem;
            font-weight: 500;
        }

        .profile-role {
            font-size: 0.8rem;
            color: var(--gray-color);
        }

        .nav-menu {
            margin-top: 1rem;
            padding: 0 1rem;
        }

        .nav-section {
            margin-bottom: 1.5rem;
        }

        .nav-section-title {
            font-size: 0.8rem;
            text-transform: uppercase;
            color: var(--gray-color);
            margin-bottom: 0.8rem;
            padding-left: 0.8rem;
            letter-spacing: 0.5px;
        }

        .nav-item {
            margin-bottom: 0.3rem;
            border-radius: var(--border-radius);
            transition: var(--transition);
        }

        .nav-item:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .nav-item.active {
            background-color: var(--primary-color);
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.8rem 1rem;
            color: white;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .nav-link i {
            margin-right: 0.8rem;
            font-size: 1rem;
            width: 20px;
            text-align: center;
        }

        .nav-link .badge {
            margin-left: auto;
            background-color: var(--accent-color);
            color: white;
            font-size: 0.7rem;
            padding: 0.2rem 0.5rem;
            border-radius: 10px;
        }

        /* Main Content Styles */
        .main-content {
            flex: 1;
            padding: 2rem;
            overflow-y: auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e0e0e0;
        }

        .header h2 {
            font-size: 1.8rem;
            color: var(--dark-color);
            font-weight: 600;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-dropdown {
            position: relative;
            display: inline-block;
        }

        .user-dropdown-btn {
            display: flex;
            align-items: center;
            background: none;
            border: none;
            cursor: pointer;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 0.5rem;
            border: 2px solid var(--primary-color);
        }

        .user-name {
            font-weight: 500;
            margin-right: 0.5rem;
        }

        .dropdown-menu {
            display: none;
            position: absolute;
            right: 0;
            background-color: white;
            min-width: 200px;
            box-shadow: var(--box-shadow);
            border-radius: var(--border-radius);
            z-index: 100;
            padding: 0.5rem 0;
        }

        .dropdown-menu.show {
            display: block;
            animation: fadeIn 0.3s ease-out;
        }

        .dropdown-item {
            display: block;
            padding: 0.5rem 1rem;
            color: var(--dark-color);
            text-decoration: none;
            transition: var(--transition);
        }

        .dropdown-item:hover {
            background-color: #f8f9fa;
        }

        .dropdown-divider {
            height: 1px;
            background-color: #eee;
            margin: 0.5rem 0;
        }

        /* Dashboard Cards */
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 1.5rem;
            transition: var(--transition);
            cursor: pointer;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .card-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .card-icon.patients {
            background: linear-gradient(45deg, var(--primary-color), var(--info-color));
        }

        .card-icon.doctors {
            background: linear-gradient(45deg, var(--success-color), var(--secondary-color));
        }

        .card-icon.assistants {
            background: linear-gradient(45deg, #8E44AD, #9B59B6);
        }

        .card-icon.appointments {
            background: linear-gradient(45deg, #E74C3C, #E67E22);
        }

        .card-icon.hospitalized {
            background: linear-gradient(45deg, #16A085, #1ABC9C);
        }

        .card-icon.prescriptions {
            background: linear-gradient(45deg, #D35400, #E67E22);
        }

        .card-icon.externals {
            background: linear-gradient(45deg, #2980B9, #3498DB);
        }

        .card-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--dark-color);
        }

        .card-title {
            font-size: 0.9rem;
            color: #666;
        }

        .card-footer {
            margin-top: 1rem;
            font-size: 0.8rem;
            color: var(--gray-color);
            display: flex;
            align-items: center;
        }

        .card-footer i {
            margin-right: 0.3rem;
        }

        /* Tables */
        .table-container {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 1.5rem;
            margin-bottom: 2rem;
            overflow-x: auto;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: nowrap;
            gap: 15px;
        }

        .table-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--dark-color);
        }

        .table-actions {
            display: flex;
            gap: 0.4rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
            white-space: nowrap;
        }

        th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: var(--dark-color);
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
        }

        tr:hover {
            background-color: #f9f9f9;
        }

        .avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 0.5rem;
        }

        .user-cell {
            display: flex;
            align-items: center;
        }

        .user-name {
            font-weight: 500;
        }

        .user-email {
            font-size: 0.8rem;
            color: var(--gray-color);
        }

        .badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .modal {
            display: none;
            /* Caché par défaut */
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.6);
            justify-content: center;
            align-items: center;
        }

        .modal.show {
            display: flex;
            /* Affiché quand la classe 'show' est ajoutée */
        }

        .modal-content {
            background-color: #fefefe;
            margin: auto;
            padding: 0;
            border: 1px solid #888;
            width: 90%;
            max-width: 600px;
            border-radius: var(--border-radius);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            animation: fadeIn 0.3s;
        }

        .modal-header {
            padding: 1rem 1.5rem;
            background-color: var(--primary-color);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #dee2e6;
            border-top-left-radius: var(--border-radius);
            border-top-right-radius: var(--border-radius);
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
        }

        .modal-close {
            color: white;
            font-size: 1.8rem;
            font-weight: bold;
            cursor: pointer;
            background: none;
            border: none;
        }

        .modal-body {
            padding: 1.5rem;
            max-height: 70vh;
            overflow-y: auto;
        }

        .modal-footer {
            padding: 1rem 1.5rem;
            display: flex;
            justify-content: flex-end;
            gap: 0.8rem;
            border-top: 1px solid #e9ecef;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: scale(0.95);
            }

            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .badge-primary {
            background-color: #E3F2FD;
            color: #1976D2;
        }

        .badge-success {
            background-color: #E8F5E9;
            color: #388E3C;
        }

        .badge-warning {
            background-color: #FFF3E0;
            color: #F57C00;
        }

        .badge-danger {
            background-color: #FFEBEE;
            color: #D32F2F;
        }

        .badge-info {
            background-color: #E1F5FE;
            color: #0288D1;
        }

        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-confirme {
            background-color: #d4edda;
            color: #155724;
        }

        .status-en_attente {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-annule {
            background-color: #f8d7da;
            color: #721c24;
        }

        .action-btn {
            padding: 0.3rem 0.6rem;
            border-radius: 4px;
            font-size: 0.8rem;
            cursor: pointer;
            border: none;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.3rem;
        }

        .action-btn i {
            margin-right: 0.3rem;
        }

        .btn-view {
            background-color: #E3F2FD;
            color: #1976D2;
        }

        .btn-view:hover {
            background-color: #BBDEFB;
        }

        .btn-edit {
            background-color: #FFF3E0;
            color: #E65100;
        }

        .btn-edit:hover {
            background-color: #FFE0B2;
        }

        .btn-delete {
            background-color: #FFEBEE;
            color: #D32F2F;
        }

        .btn-delete:hover {
            background-color: #FFCDD2;
        }

        /* Forms */
        .form-container {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .form-title {
            font-size: 1.5rem;
            color: var(--dark-color);
            margin-bottom: 1.5rem;
            padding-bottom: 0.8rem;
            border-bottom: 1px solid #eee;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark-color);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: var(--transition);
        }

        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(58, 123, 213, 0.1);
        }

        .form-row {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .form-col {
            flex: 1;
        }

        .profile-picture-container {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .profile-picture {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 1.5rem;
            border: 3px solid var(--primary-color);
        }

        .profile-picture-upload {
            display: flex;
            flex-direction: column;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius);
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn i {
            margin-right: 0.5rem;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: #2980b9;
        }

        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
        }

        .btn-outline:hover {
            background-color: rgba(52, 152, 219, 0.1);
        }

        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }

        .btn-danger:hover {
            background-color: #c0392b;
        }

        .btn-success {
            background-color: var(--success-color);
            color: white;
        }

        .btn-success:hover {
            background-color: #219653;
        }

        /* Tabs */
        .tabs {
            display: flex;
            border-bottom: 1px solid #ddd;
            margin-bottom: 1.5rem;
        }

        .tab-btn {
            padding: 0.8rem 1.5rem;
            background: none;
            border: none;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            font-weight: 500;
            color: #666;
            transition: var(--transition);
        }

        .tab-btn.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* User Details */
        .user-details-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .user-info-card {
            display: flex;
            align-items: center;
            margin-bottom: 2rem;
        }

        .user-avatar-lg {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 1.5rem;
            border: 3px solid var(--primary-color);
        }

        .user-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-top: 1rem;
        }

        .meta-item {
            display: flex;
            align-items: center;
            font-size: 0.9rem;
            color: #666;
        }

        .meta-item i {
            margin-right: 0.5rem;
            color: var(--primary-color);
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            animation: fadeInUp 0.3s ease-out;
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark-color);
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: flex-end;
            gap: 0.8rem;
        }

        /* Alert */
        .alert {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: var(--border-radius);
            border: 1px solid transparent;
        }

        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }

        .alert-error {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .dashboard-cards {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 992px) {
            .admin-container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                height: auto;
                position: static;
            }

            .main-content {
                padding: 1.5rem;
            }

            .form-row {
                flex-direction: column;
                gap: 0;
            }
        }

        @media (max-width: 768px) {
            .dashboard-cards {
                grid-template-columns: 1fr;
            }

            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .header-actions {
                width: 100%;
                justify-content: space-between;
            }

            .user-dropdown {
                width: 100%;
            }

            .user-dropdown-btn {
                width: 100%;
                justify-content: space-between;
            }

            .dropdown-menu {
                width: 100%;
            }

            .table-actions {
                flex-direction: column;
                gap: 0.5rem;
                align-items: flex-end;
            }
        }

        /* Animations */
        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in {
            animation: fadeIn 0.5s ease-out;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--secondary-color);
        }

        /* No Data */
        .no-data {
            padding: 2rem;
            text-align: center;
            color: #666;
            background-color: #f9f9f9;
            border-radius: var(--border-radius);
            margin: 1rem 0;
        }

        /* Loading */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, .3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Export Buttons */
        .export-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        /* Settings Section */
        .settings-section {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 20px;
            margin-bottom: 20px;
        }

        .settings-section h3 {
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .settings-option {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #f5f5f5;
        }

        .settings-option:last-child {
            border-bottom: none;
        }

        /* Toggle Switch */
        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 24px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked+.slider {
            background-color: var(--primary-color);
        }

        input:checked+.slider:before {
            transform: translateX(26px);
        }
    </style>

    <style>
        /* Transition pour la sidebar */
        .sidebar {
            transition: width 0.3s ease;
        }

        /* Sidebar réduite */
        .sidebar.collapsed {
            width: 80px;
        }

        .sidebar.collapsed .profile-info,
        .sidebar.collapsed .nav-section-title,
        .sidebar.collapsed .nav-link span,
        .sidebar.collapsed .nav-link .badge {
            display: none;
        }

        .sidebar.collapsed .nav-link {
            justify-content: center;
            padding: 0.5rem 0;
            text-align: center;
        }

        .sidebar.collapsed .logo h1 {
            display: none;
        }

        .sidebar.collapsed .logo i {
            margin-right: 0;
        }

        /* Bouton de basculement EXTERNE */
        .toggle-sidebar-btn-external {
            position: fixed;
            top: 5px;
            left: 280px;
            /* Position initiale quand sidebar est ouverte */
            background: var(--primary-color);
            border: none;
            color: white;
            text-align: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }

        .nav-item a i {
            font-size: 20px;
            text-align: center;
        }

        /* Position du bouton quand sidebar est réduite */
        .admin-container.collapsed .toggle-sidebar-btn-external {
            left: 80px;
            /* Position quand sidebar est réduite */
        }

        /* Ajustement du contenu principal */
        .main-content {
            transition: margin-left 0.3s ease;
        }

        .admin-container.collapsed .main-content {
            margin-left: 0;
        }

        /* Responsive - cacher le bouton sur mobile */
        @media (max-width: 992px) {
            .toggle-sidebar-btn-external {
                display: none;
            }
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <i class="fas fa-heartbeat"></i>
                    <h1>Gestion Cabinet</h1>

                </div>



                <div class="profile-info" id="profileDropdownBtn">
                    <img src="<?php echo htmlspecialchars($_SESSION['profile_picture'] ?? 'img.jpg'); ?>"
                        class="profile-pic admin-profile-pic" alt="Profile">
                    <div>
                        <div class="profile-name"><?php echo htmlspecialchars($_SESSION['ad_name'] ?? 'Admin'); ?></div>
                        <div class="profile-role">Administrateur</div>
                    </div>
                </div>
            </div>

            <nav class="nav-menu">
                <div class="nav-section">
                    <div class="nav-section-title">Tableau de bord</div>
                    <div class="nav-item <?php echo $current_view == 'dashboard' ? 'active' : ''; ?>">
                        <a href="?view=dashboard" class="nav-link">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                    </div>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Gestion</div>
                    <div class="nav-item <?php echo $current_view == 'users' ? 'active' : ''; ?>">
                        <a href="?view=users" class="nav-link">
                            <i class="fas fa-users-cog"></i>
                            <span>Utilisateurs</span>
                        </a>
                    </div>
                    <div class="nav-item <?php echo $current_view == 'patients' ? 'active' : ''; ?>">
                        <a href="?view=patients" class="nav-link">
                            <i class="fas fa-user-injured"></i>
                            <span>Patients</span>
                            <span class="badge"><?php echo $stats['patients']; ?></span>
                        </a>
                    </div>
                    <div class="nav-item <?php echo $current_view == 'appointments' ? 'active' : ''; ?>">
                        <a href="?view=appointments" class="nav-link">
                            <i class="fas fa-calendar-check"></i>
                            <span>Rendez-vous</span>
                            <span class="badge"><?php echo $stats['rdv_aujourdhui'] + $stats['rdv_prochains']; ?></span>
                        </a>
                    </div>
                    <div class="nav-item <?php echo $current_view == 'hospitalized' ? 'active' : ''; ?>">
                        <a href="?view=hospitalized" class="nav-link">
                            <i class="fas fa-procedures"></i>
                            <span>Hospitalisés</span>
                            <span class="badge"><?php echo $stats['hospitalises']; ?></span>
                        </a>
                    </div>
                    <div class="nav-item <?php echo $current_view == 'prescriptions' ? 'active' : ''; ?>">
                        <a href="?view=prescriptions" class="nav-link">
                            <i class="fas fa-prescription-bottle-alt"></i>
                            <span>Ordonnances</span>
                            <span class="badge"><?php echo $stats['ordonnances']; ?></span>
                        </a>
                    </div>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Rapports</div>
                    <div class="nav-item <?php echo $current_view == 'statistics' ? 'active' : ''; ?>">
                        <a href="?view=statistics" class="nav-link">
                            <i class="fas fa-chart-line"></i>
                            <span>Statistiques</span>
                        </a>
                    </div>
                    
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Sortie</div>
                    <!-- <div class="nav-item <?php echo $current_view == 'settings' ? 'active' : ''; ?>">
                        <a href="?view=settings" class="nav-link">
                            <i class="fas fa-cog"></i>
                            <span>Paramètres</span>
                        </a>
                    </div> -->
                    <div class="nav-item">
                        <a href="deconnexion.php" class="nav-link">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Déconnexion</span>
                        </a>
                    </div>
                </div>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <button class="toggle-sidebar-btn-external" id="toggleSidebarBtn">
                <i class="fas fa-bars"></i>
            </button>
            <div class="header">
                <h2>
                    <?php
                    switch ($current_view) {
                        case 'dashboard':
                            echo 'Tableau de Bord';
                            break;
                        case 'users':
                            echo 'Gestion des Utilisateurs';
                            break;
                        case 'patients':
                            echo 'Gestion des Patients';
                            break;
                        case 'appointments':
                            echo 'Rendez-vous';
                            break;
                        case 'hospitalized':
                            echo 'Patients Hospitalisés';
                            break;
                        case 'prescriptions':
                            echo 'Ordonnances';
                            break;
                        case 'user_details':
                            echo 'Détails Utilisateur';
                            break;
                        case 'statistics':
                            echo 'Statistiques';
                            break;
                        case 'settings':
                            echo 'Paramètres';
                            break;
                        default:
                            echo 'Tableau de Bord';
                    }
                    ?>
                </h2>

                <div class="header-actions">
                    <div class="user-dropdown">
                        <button class="user-dropdown-btn" id="userDropdownBtn">
                            <img src="<?php echo htmlspecialchars($_SESSION['profile_picture'] ?? 'img.jpg'); ?>"
                                class="user-avatar admin-profile-pic" alt="User">
                            <span class="user-name"><?php echo htmlspecialchars($_SESSION['ad_name'] ?? 'Admin'); ?></span>
                            <i class="fas fa-chevron-down"></i>
                        </button>

                        <div class="dropdown-menu" id="userDropdownMenu">
                            <a href="#" class="dropdown-item" id="profileBtn">
                                <i class="fas fa-user"></i> Mon Profil
                            </a>
                            <a href="#" class="dropdown-item" id="changePasswordBtn">
                                <i class="fas fa-key"></i> Changer Mot de Passe
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="deconnexion.php" class="dropdown-item">
                                <i class="fas fa-sign-out-alt"></i> Déconnexion
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Affichage des messages -->

            <!-- Dashboard View -->
            <?php if ($current_view == 'dashboard'): ?>
                <div class="dashboard-cards">
                    <div class="card fade-in" onclick="window.location.href='?view=patients'">
                        <div class="card-header">
                            <div>
                                <div class="card-value"><?php echo $stats['patients']; ?></div>
                                <div class="card-title">Patients</div>
                            </div>
                            <div class="card-icon patients">
                                <i class="fas fa-user-injured"></i>
                            </div>
                        </div>
                        <div class="card-footer">
                            <i class="fas fa-procedures"></i> <?php echo $stats['hospitalises']; ?> hospitalisés
                            <span style="margin: 0 10px">|</span>
                            <i class="fas fa-home"></i> <?php echo $stats['patients_externes']; ?> externes
                        </div>
                    </div>

                    <div class="card fade-in" onclick="window.location.href='?view=users&departement=medecin'">
                        <div class="card-header">
                            <div>
                                <div class="card-value"><?php echo $stats['medecins']; ?></div>
                                <div class="card-title">Médecins</div>
                            </div>
                            <div class="card-icon doctors">
                                <i class="fas fa-user-md"></i>
                            </div>
                        </div>
                        <div class="card-footer">
                            <i class="fas fa-stethoscope"></i> Spécialités variées
                        </div>
                    </div>

                    <div class="card fade-in" onclick="window.location.href='?view=users&departement=assistant'">
                        <div class="card-header">
                            <div>
                                <div class="card-value"><?php echo $stats['assistants']; ?></div>
                                <div class="card-title">Assistants</div>
                            </div>
                            <div class="card-icon assistants">
                                <i class="fas fa-user-nurse"></i>
                            </div>
                        </div>
                        <div class="card-footer">
                            <i class="fas fa-hands-helping"></i> Support médical
                        </div>
                    </div>

                    <div class="card fade-in" onclick="window.location.href='?view=appointments'">
                        <div class="card-header">
                            <div>
                                <div class="card-value"><?php echo $stats['rdv_aujourdhui']; ?></div>
                                <div class="card-title">RDV Aujourd'hui</div>
                            </div>
                            <div class="card-icon appointments">
                                <i class="fas fa-calendar-day"></i>
                            </div>
                        </div>
                        <div class="card-footer">
                            <i class="fas fa-calendar-check"></i> <?php echo $stats['rdv_prochains']; ?> prochains RDV
                        </div>
                    </div>

                    <div class="card fade-in" onclick="window.location.href='?view=hospitalized'">
                        <div class="card-header">
                            <div>
                                <div class="card-value"><?php echo $stats['hospitalises']; ?></div>
                                <div class="card-title">Hospitalisés</div>
                            </div>
                            <div class="card-icon hospitalized">
                                <i class="fas fa-procedures"></i>
                            </div>
                        </div>
                        <div class="card-footer">
                            <i class="fas fa-heartbeat"></i> En cours de traitement
                        </div>
                    </div>

                    <div class="card fade-in" onclick="window.location.href='?view=prescriptions'">
                        <div class="card-header">
                            <div>
                                <div class="card-value"><?php echo $stats['ordonnances']; ?></div>
                                <div class="card-title">Ordonnances</div>
                            </div>
                            <div class="card-icon prescriptions">
                                <i class="fas fa-prescription-bottle-alt"></i>
                            </div>
                        </div>
                        <div class="card-footer">
                            <i class="fas fa-history"></i> 30 derniers jours
                        </div>
                    </div>
                </div>

                <!-- Recent Appointments -->
                <div class="table-container fade-in">
                    <div class="table-header">
                        <div class="table-title">Rendez-vous Récents</div>
                        <div class="table-actions">
                            <a href="?view=appointments" class="btn btn-outline">
                                <i class="fas fa-list"></i> Voir tous
                            </a>
                        </div>
                    </div>

                    <?php
                    $recent_appointments = $conn->query("
                        SELECT r.id_rdv, r.date_rdv, r.heure, r.lieu, r.motif, r.statut, 
                               up.nom AS patient_nom, up.prenom AS patient_prenom,
                               um.nom AS medecin_nom, um.prenom AS medecin_prenom
                        FROM rendezvous r
                        JOIN traitement t ON r.id_traitement = t.id_traitement
                        JOIN utilisateur up ON t.id_patient = up.id_utilisateur
                        JOIN utilisateur um ON t.id_medecin = um.id_utilisateur
                        WHERE r.date_rdv >= CURDATE()
                        ORDER BY r.date_rdv, r.heure
                        LIMIT 5
                    ");

                    if ($recent_appointments->num_rows > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Patient</th>
                                    <th>Médecin</th>
                                    <th>Motif</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($appointment = $recent_appointments->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y', strtotime($appointment['date_rdv'])); ?> à <?php echo date('H:i', strtotime($appointment['heure'])); ?></td>
                                        <td class="user-cell">
                                            <img src="patient.png" class="avatar" alt="Patient">
                                            <div>
                                                <div class="user-name"><?php echo htmlspecialchars($appointment['patient_prenom'] . ' ' . $appointment['patient_nom']); ?></div>
                                                <div class="user-email"><?php echo htmlspecialchars($appointment['lieu']); ?></div>
                                            </div>
                                        </td>
                                        <td>Dr. <?php echo htmlspecialchars($appointment['medecin_prenom'] . ' ' . $appointment['medecin_nom']); ?></td>
                                        <td><?php echo htmlspecialchars($appointment['motif']); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower($appointment['statut']); ?>">
                                                <?php echo ucfirst($appointment['statut']); ?>
                                            </span>
                                        </td>
                                        
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-calendar-times fa-2x" style="margin-bottom: 1rem;"></i>
                            <p>Aucun rendez-vous prévu aujourd'hui</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Hospitalizations -->
                <div class="table-container fade-in">
                    <div class="table-header">
                        <div class="table-title">Patients Hospitalisés Récemment</div>
                        <div class="table-actions">
                            <a href="?view=hospitalized" class="btn btn-outline">
                                <i class="fas fa-list"></i> Voir tous
                            </a>
                        </div>
                    </div>

                    <?php
                    $recent_hospitalized = $conn->query("
                        SELECT h.id_hospitalisation, h.date_entree, h.date_sortie, h.service,
                               up.id_utilisateur AS id_patient, up.nom AS patient_nom, up.prenom AS patient_prenom,
                               um.nom AS medecin_nom, um.prenom AS medecin_prenom
                        FROM hospitalisation h
                        JOIN utilisateur up ON h.id_patient = up.id_utilisateur
                        JOIN traitement t ON h.id_traitement = t.id_traitement
                        JOIN utilisateur um ON t.id_medecin = um.id_utilisateur
                        WHERE h.date_sortie IS NULL OR h.date_sortie >= CURDATE()
                        ORDER BY h.date_entree DESC
                        LIMIT 5
                    ");

                    if ($recent_hospitalized->num_rows > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Patient</th>
                                    <th>Service</th>
                                    <th>Date Entrée</th>
                                    <th>Date Sortie</th>
                                    <th>Médecin</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($patient = $recent_hospitalized->fetch_assoc()): ?>
                                    <tr>
                                        <td class="user-cell">
                                            <img src="patient.png" class="avatar" alt="Patient">
                                            <div>
                                                <div class="user-name"><?php echo htmlspecialchars($patient['patient_prenom'] . ' ' . $patient['patient_nom']); ?></div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($patient['service']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($patient['date_entree'])); ?></td>
                                        <td><?php echo $patient['date_sortie'] ? date('d/m/Y', strtotime($patient['date_sortie'])) : 'En cours'; ?></td>
                                        <td>Dr. <?php echo htmlspecialchars($patient['medecin_prenom'] . ' ' . $patient['medecin_nom']); ?></td>
                                        <td>
                                            <button class="action-btn btn-view" onclick="window.location.href='?user_id=<?php echo $patient['id_patient']; ?>'">
                                                <i class="fas fa-eye"></i> Dossier
                                            </button>

                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-procedures fa-2x" style="margin-bottom: 1rem;"></i>
                            <p>Aucun patient actuellement hospitalisé</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php elseif ($current_view == 'users' || $current_view == 'patients'): ?>
                <div class="table-container fade-in">
                    <div class="table-header">
                        <div class="table-title">
                            <?php echo $current_view == 'patients' ? 'Gestion des Patients' : 'Gestion des Utilisateurs'; ?>
                        </div>
                        <div class="table-actions">
                            <form method="get" action="" style="display: flex; gap: 0.8rem;">
                                <input type="hidden" name="view" value="<?php echo $current_view; ?>">

                                <?php if ($current_view == 'users'): ?>
                                    <select name="departement" onchange="this.form.submit()" class="form-control" style="width: 180px;">
                                        <option value="tous" <?php echo (!isset($_GET['departement']) || $_GET['departement'] == 'tous' ? 'selected' : ''); ?>>Tous les rôles</option>
                                        <option value="medecin" <?php echo isset($_GET['departement']) && $_GET['departement'] == 'medecin' ? 'selected' : ''; ?>>Médecins</option>
                                        <option value="assistant" <?php echo isset($_GET['departement']) && $_GET['departement'] == 'assistant' ? 'selected' : ''; ?>>Assistants</option>
                                    </select>
                                <?php endif; ?>

                                <div style="display: flex;">
                                    <input type="text" name="search" placeholder="Rechercher..." value="<?php echo htmlspecialchars($search); ?>" class="form-control" style="border-radius: var(--border-radius) 0 0 var(--border-radius);">
                                    <button type="submit" class="btn btn-primary" style="border-radius: 0 var(--border-radius) var(--border-radius) 0;">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>

                                <?php if ($current_view == 'patients'): ?>
                                    <a href="#" class="btn btn-success" id="addPatientBtn">
                                        <i class="fas fa-plus"></i> Nouveau Patient
                                    </a>
                                <?php else: ?>
                                    <a href="#" class="btn btn-success" id="addUserBtn">
                                        <i class="fas fa-plus"></i> Nouvel Utilisateur
                                    </a>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>

                    <?php if (!empty($all_users)): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Utilisateur</th>
                                    <th>Email</th>
                                    <th>Téléphone</th>
                                    <!-- DÉBUT DE LA MODIFICATION : Changer l'en-tête du tableau -->
                                    <?php if ($current_view == 'patients'): ?>
                                        <th>Statut</th> <!-- Affiche "Statut" pour les patients -->
                                    <?php else: ?>
                                        <th>Rôle</th> <!-- Affiche "Rôle" pour les autres utilisateurs -->
                                    <?php endif; ?>
                                    <!-- FIN DE LA MODIFICATION -->
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_users as $row): ?>
                                    <?php
                                    $avatar = 'default-profile.png';
                                    $roleClass = 'badge-primary';

                                    switch (strtolower($row['rôle'])) {
                                        case 'medecin':
                                            $avatar = 'doc-icon.png';
                                            $roleClass = 'badge-success';
                                            break;
                                        case 'assistant':
                                            $avatar = 'images.jpeg';
                                            $roleClass = 'badge-warning';
                                            break;
                                        case 'admin':
                                            $roleClass = 'badge-danger';
                                            $admin_photo = ($row['id'] == $_SESSION['user_id']) ? ($_SESSION['profile_picture'] ?? 'img.jpg') : 'img.jpg';
                                            $avatar = $admin_photo;
                                            break;
                                        case  'patient':
                                            $avatar = 'patient.png';
                                    }
                                    ?>
                                    <tr>
                                        <td class="user-cell">
                                            <img src="<?php echo htmlspecialchars($avatar); ?>"
                                                class="avatar <?php if ($row['rôle'] == 'admin') echo 'admin-profile-pic'; ?>"
                                                alt="User">
                                            <div>
                                                <div class="user-name"><?php echo htmlspecialchars($row['prenom'] . ' ' . $row['nom']); ?></div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                                        <td><?php echo htmlspecialchars($row['telephone']); ?></td>
                                        <td>
                                            <!-- DÉBUT DE LA MODIFICATION : Affichage conditionnel du statut ou du rôle -->
                                            <?php if ($current_view == 'patients'): ?>
                                                <?php
                                                // On choisit la couleur du badge en fonction du statut
                                                $statut_class = ($row['statut_hospitalisation'] == 'Hospitalisé') ? 'badge-danger' : 'badge-success';
                                                ?>
                                                <span class="badge <?php echo $statut_class; ?>">
                                                    <?php echo htmlspecialchars($row['statut_hospitalisation']); ?>
                                                </span>
                                            <?php else: ?>
                                                <?php
                                                // Logique existante pour les badges de rôle
                                                $roleClass = 'badge-primary';
                                                switch (strtolower($row['rôle'])) {
                                                    case 'medecin':
                                                        $roleClass = 'badge-success';
                                                        break;
                                                    case 'assistant':
                                                        $roleClass = 'badge-warning';
                                                        break;
                                                    case 'admin':
                                                        $roleClass = 'badge-danger';
                                                        break;
                                                }
                                                ?>
                                                <span class="badge <?php echo $roleClass; ?>">
                                                    <?php echo htmlspecialchars($row['rôle']); ?>
                                                </span>
                                            <?php endif; ?>
                                            <!-- FIN DE LA MODIFICATION -->
                                        </td>
                                        <td>
                                            <button class="action-btn btn-view" onclick="window.location.href='?user_id=<?php echo $row['id']; ?>'">
                                                <i class="fas fa-eye"></i> Voir
                                            </button>

                                            <!-- VERSION FINALE ET UNIVERSELLE DU BOUTON MODIFIER -->

                                            <!-- NOUVEAU BOUTON CORRIGÉ ET UNIVERSEL -->
                                            <button class="action-btn btn-edit"
                                                data-modal-open="editUserModal"
                                                data-user-id="<?php echo $row['id']; ?>"
                                                data-nom="<?php echo htmlspecialchars($row['nom']); ?>"
                                                data-prenom="<?php echo htmlspecialchars($row['prenom']); ?>"
                                                data-email="<?php echo htmlspecialchars($row['email']); ?>"
                                                data-telephone="<?php echo htmlspecialchars($row['telephone']); ?>"
                                                data-role="<?php echo htmlspecialchars($row['rôle']); ?>"

                                                <?php if ($row['rôle'] == 'patient'): ?>
                                                data-date-naissance="<?php echo htmlspecialchars($row['date_naissance'] ?? ''); ?>"
                                                data-sexe="<?php echo htmlspecialchars($row['sexe'] ?? ''); ?>"
                                                data-adresse="<?php echo htmlspecialchars($row['adresse'] ?? ''); ?>"
                                                data-statut-hospitalisation="<?php echo htmlspecialchars($row['statut_hospitalisation'] ?? 'Externe'); ?>"
                                                <?php elseif ($row['rôle'] == 'medecin'): ?>
                                                data-specialite="<?php echo htmlspecialchars($row['spécialité'] ?? ''); ?>"
                                                <?php endif; ?>>
                                                <i class="fas fa-edit"></i> Modifier
                                            </button>




                                            <button class="action-btn btn-delete" onclick="confirmDelete(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars(addslashes($row['prenom'] . ' ' . $row['nom'])); ?>', '<?php echo $row['rôle']; ?>')">
                                                <i class="fas fa-trash"></i> Supprimer
                                            </button>
                                        </td>

                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-user-times fa-2x" style="margin-bottom: 1rem;"></i>
                            <p>Aucun utilisateur trouvé</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php elseif ($current_view == 'appointments'): ?>
                <div class="table-container fade-in">
                    <div class="table-header">
                        <div class="table-title">Gestion des Rendez-vous</div>
                        <div class="table-actions">
                            <form method="get" action="" style="display: flex;">
                                <input type="hidden" name="view" value="appointments">
                                <input type="text" name="search" placeholder="Rechercher..." value="<?php echo htmlspecialchars($search); ?>" class="form-control" style="border-radius: var(--border-radius) 0 0 var(--border-radius);">
                                <button type="submit" class="btn btn-primary" style="border-radius: 0 var(--border-radius) var(--border-radius) 0;">
                                    <i class="fas fa-search"></i>
                                </button>
                            </form>
                            <a href="#" class="btn btn-success" id="addAppointmentBtn">
                                <i class="fas fa-plus"></i> Nouveau RDV
                            </a>
                        </div>
                    </div>

                    <?php if (!empty($appointments)): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Patient</th>
                                    <th>Médecin</th>
                                    <th>Motif</th>
                                    <th>Lieu</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($appointments as $appointment): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y', strtotime($appointment['date_rdv'])); ?> à <?php echo date('H:i', strtotime($appointment['heure'])); ?></td>
                                        <td class="user-cell">
                                            <img src="patient.png" class="avatar" alt="Patient">
                                            <div>
                                                <div class="user-name"><?php echo htmlspecialchars($appointment['patient_prenom'] . ' ' . $appointment['patient_nom']); ?></div>
                                            </div>
                                        </td>
                                        <td>Dr. <?php echo htmlspecialchars($appointment['medecin_prenom'] . ' ' . $appointment['medecin_nom']); ?></td>
                                        <td><?php echo htmlspecialchars($appointment['motif']); ?></td>
                                        <td><?php echo htmlspecialchars($appointment['lieu']); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower($appointment['statut']); ?>">
                                                <?php echo ucfirst($appointment['statut']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            

                                            <button class="action-btn btn-edit"
                                                data-modal-open="editAppointmentModal"
                                                data-rdv-id="<?php echo $appointment['id_rdv']; ?>"
                                                data-date-rdv="<?php echo htmlspecialchars($appointment['date_rdv']); ?>"
                                                data-heure="<?php echo htmlspecialchars($appointment['heure']); ?>"
                                                data-motif="<?php echo htmlspecialchars($appointment['motif']); ?>"
                                                data-lieu="<?php echo htmlspecialchars($appointment['lieu']); ?>"
                                                data-statut="<?php echo htmlspecialchars($appointment['statut']); ?>">
                                                <i class="fas fa-edit"></i> Modifier
                                            </button>

                                            <button class="action-btn btn-delete" onclick="cancelAppointment(<?php echo $appointment['id_rdv']; ?>)">
                                                <i class="fas fa-times"></i> Annuler
                                            </button>
                                        </td>

                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-calendar-times fa-2x" style="margin-bottom: 1rem;"></i>
                            <p>Aucun rendez-vous trouvé</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php elseif ($current_view == 'prescriptions'): ?>
                <div class="table-container fade-in">
                    <div class="table-header">
                        <div class="table-title">Ordonnances Médicales</div>
                        <div class="table-actions">
                            <form method="get" action="" style="display: flex;">
                                <input type="hidden" name="view" value="prescriptions">
                                <input type="text" name="search" placeholder="Rechercher..." value="<?php echo htmlspecialchars($search); ?>" class="form-control" style="border-radius: var(--border-radius) 0 0 var(--border-radius);">
                                <button type="submit" class="btn btn-primary" style="border-radius: 0 var(--border-radius) var(--border-radius) 0;">
                                    <i class="fas fa-search"></i>
                                </button>
                            </form>
                            
                        </div>
                    </div>

                    <?php if (!empty($prescriptions)): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Patient</th>
                                    <th>Médecin</th>
                                    <th>Médicaments/Traitement</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($prescriptions as $prescription): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y', strtotime($prescription['date_ordonnance'])); ?></td>
                                        <td class="user-cell">
                                            <img src="patient.png" class="avatar" alt="Patient">
                                            <div>
                                                <div class="user-name"><?php echo htmlspecialchars($prescription['patient_prenom'] . ' ' . $prescription['patient_nom']); ?></div>
                                            </div>
                                        </td>
                                        <td>Dr. <?php echo htmlspecialchars($prescription['medecin_prenom'] . ' ' . $prescription['medecin_nom']); ?></td>
                                        <td>
                                            <?php if (strpos($prescription['médicaments'], 'uploads/') === 0): ?>
                                                <a href="<?php echo htmlspecialchars($prescription['médicaments']); ?>" target="_blank" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-file-pdf"></i> Voir l'ordonnance
                                                </a>
                                            <?php else: ?>
                                                <?php echo nl2br(htmlspecialchars(substr($prescription['médicaments'], 0, 100) . (strlen($prescription['médicaments']) > 100 ? '...' : ''))); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            
                                            <a href="<?php echo strpos($prescription['médicaments'], 'uploads/') === 0 ? $prescription['médicaments'] : 'data:text/plain;charset=utf-8,' . urlencode($prescription['médicaments']); ?>"
                                                download="ordonnance_<?php echo $prescription['patient_prenom'] . '_' . $prescription['patient_nom'] . '_' . $prescription['date_ordonnance']; ?>.<?php echo strpos($prescription['médicaments'], 'uploads/') === 0 ? pathinfo($prescription['médicaments'], PATHINFO_EXTENSION) : 'txt'; ?>"
                                                class="action-btn btn-edit">
                                                <i class="fas fa-download"></i> Télécharger
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-prescription-bottle-alt fa-2x" style="margin-bottom: 1rem;"></i>
                            <p>Aucune ordonnance trouvée</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php elseif ($current_view == 'hospitalized'): ?>
                <div class="table-container fade-in">
                    <div class="table-header">
                        <div class="table-title">Patients Hospitalisés</div>
                        <div class="table-actions">
                            <form method="get" action="" style="display: flex; gap: 0.8rem;">
                                <input type="hidden" name="view" value="hospitalized">
                                <input type="text" name="search" placeholder="Rechercher par nom..." value="<?php echo htmlspecialchars($search); ?>" class="form-control">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                            </form>
                            <!-- Bouton corrigé pour ouvrir la modale en mode "ajout" -->
                            <button class="btn btn-success" data-modal-open="hospitalizationModal" id="addHospitalizationBtn">
                                <i class="fas fa-plus"></i> Nouvelle Hospitalisation
                            </button>
                        </div>
                    </div>

                    <?php if (!empty($hospitalized)): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Patient</th>
                                    <th>Service</th>
                                    <th>Date Entrée</th>
                                    <th>Date Sortie</th>
                                    <th>Médecin</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($hospitalized as $patient): ?>
                                    <tr>
                                        <td class="user-cell">
                                            <img src="patient.png" class="avatar" alt="Patient">
                                            <div>
                                                <div class="user-name"><?php echo htmlspecialchars($patient['patient_prenom'] . ' ' . $patient['patient_nom']); ?></div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($patient['service']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($patient['date_entree'])); ?></td>
                                        <td><?php echo $patient['date_sortie'] ? date('d/m/Y', strtotime($patient['date_sortie'])) : 'En cours'; ?></td>
                                        <td>Dr. <?php echo htmlspecialchars($patient['medecin_prenom'] . ' ' . $patient['medecin_nom']); ?></td>
                                        <!-- Dans la boucle foreach ($hospitalized as $patient) -->
                                        <td>
                                            <button class="action-btn btn-view" onclick="window.location.href='?user_id=<?php echo $patient['id_patient']; ?>'">
                                                <i class="fas fa-eye"></i> Dossier
                                            </button>

                                            <!-- BOUTON MODIFIER CORRIGÉ -->
                                            <button class="action-btn btn-edit"
                                                data-modal-open="hospitalizationModal"
                                                data-hosp-id="<?php echo $patient['id_hospitalisation']; ?>"
                                                data-patient-id="<?php echo $patient['id_patient']; ?>"
                                                data-medecin-id="<?php echo $patient['id_medecin']; ?>"
                                                data-service="<?php echo htmlspecialchars($patient['service']); ?>"
                                                data-date-entree="<?php echo htmlspecialchars($patient['date_entree']); ?>"
                                                data-date-sortie="<?php echo htmlspecialchars($patient['date_sortie'] ?? ''); ?>">
                                                <i class="fas fa-edit"></i> Modifier
                                            </button>

                                            <?php if (!$patient['date_sortie']): ?>
                                                <!-- BOUTON SORTIE -->
                                                <button class="action-btn btn-delete" onclick="dischargePatient(<?php echo $patient['id_hospitalisation']; ?>)">
                                                    <i class="fas fa-sign-out-alt"></i> Sortie
                                                </button>
                                            <?php endif; ?>
                                        </td>

                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-procedures fa-2x" style="margin-bottom: 1rem;"></i>
                            <p>Aucun patient actuellement hospitalisé.</p>
                        </div>
                    <?php endif; ?>
                </div>

            <?php elseif ($current_view == 'user_details' && $userDetails): ?>
                <div class="form-container fade-in">
                    <div class="user-details-header">
                        <div class="user-info-card">
                            <?php
                            // Déterminer l'image par défaut
                            $default_avatar = 'img.jpg'; // Image par défaut pour un admin
                            if ($userDetails['type'] == 'patient') $default_avatar = 'patient.png';
                            elseif ($userDetails['type'] == 'medecin') $default_avatar = 'doc-icon.png';
                            elseif ($userDetails['type'] == 'assistant') $default_avatar = 'images.jpeg';

                            // Utiliser la photo de profil si elle existe
                            $avatar_path = !empty($userDetails['info']['photo_profil']) ? $userDetails['info']['photo_profil'] : $default_avatar;

                            // Ajouter la classe spéciale si c'est le profil de l'admin connecté
                            $extra_class = ($userDetails['info']['id_utilisateur'] == $_SESSION['user_id']) ? 'admin-profile-pic' : '';
                            ?>
                            <img src="<?php echo htmlspecialchars($avatar_path); ?>"
                                class="user-avatar-lg <?php echo $extra_class; ?>" alt="User">
                            <div>
                                <h3><?php echo htmlspecialchars($userDetails['info']['prenom'] . ' ' . $userDetails['info']['nom']); ?></h3>
                                <span class="badge <?php
                                                    echo $userDetails['info']['rôle'] == 'patient' ? 'badge-primary' : ($userDetails['info']['rôle'] == 'medecin' ? 'badge-success' : ($userDetails['info']['rôle'] == 'assistant' ? 'badge-warning' : 'badge-danger'));
                                                    ?>">
                                    <?php echo ucfirst($userDetails['info']['rôle']); ?>
                                </span>

                                <div class="user-meta">
                                    <div class="meta-item">
                                        <i class="fas fa-envelope"></i>
                                        <span><?php echo htmlspecialchars($userDetails['info']['email']); ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <i class="fas fa-phone"></i>
                                        <span><?php echo htmlspecialchars($userDetails['info']['telephone']); ?></span>
                                    </div>
                                    <?php if ($userDetails['type'] == 'patient' && !empty($userDetails['details']['date_naissance'])): ?>
                                        <div class="meta-item">
                                            <i class="fas fa-birthday-cake"></i>
                                            <span><?php echo date('d/m/Y', strtotime($userDetails['details']['date_naissance'])); ?> (<?php echo date_diff(date_create($userDetails['details']['date_naissance']), date_create('today'))->y; ?> ans)</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div>
                            <a href="?view=<?php echo $userDetails['type'] == 'patient' ? 'patients' : 'users'; ?>" class="btn btn-outline">
                                <i class="fas fa-arrow-left"></i> Retour
                            </a>
                        </div>
                    </div>

                    <div class="tabs">
                        <button class="tab-btn active" onclick="openTab(event, 'info-tab')">Informations</button>
                        <?php if ($userDetails['type'] == 'patient'): ?>
                            <button class="tab-btn" onclick="openTab(event, 'rdv-tab')">Rendez-vous</button>
                            <button class="tab-btn" onclick="openTab(event, 'ordo-tab')">Ordonnances</button>
                            <button class="tab-btn" onclick="openTab(event, 'hospital-tab')">Hospitalisations</button>
                        <?php elseif ($userDetails['type'] == 'medecin'): ?>
                            <button class="tab-btn" onclick="openTab(event, 'patients-tab')">Patients</button>
                            <button class="tab-btn" onclick="openTab(event, 'consult-tab')">Consultations</button>
                        <?php endif; ?>
                    </div>

                    <!-- Info Tab -->
                    <div id="info-tab" class="tab-content active">
                        <div class="form-row">
                            <div class="form-col">
                                <h4 class="section-title">Informations Personnelles</h4>
                                <div class="form-group">
                                    <label>Nom</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($userDetails['info']['nom']); ?>" disabled>
                                </div>
                                <div class="form-group">
                                    <label>Prénom</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($userDetails['info']['prenom']); ?>" disabled>
                                </div>
                                <div class="form-group">
                                    <label>Email</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($userDetails['info']['email']); ?>" disabled>
                                </div>
                                <div class="form-group">
                                    <label>Téléphone</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($userDetails['info']['telephone']); ?>" disabled>
                                </div>
                            </div>

                            <div class="form-col">
                                <?php if ($userDetails['type'] == 'patient'): ?>
                                    <h4 class="section-title">Informations Médicales</h4>
                                    <div class="form-group">
                                        <label>Date de Naissance</label>
                                        <input type="text" class="form-control" value="<?php echo !empty($userDetails['details']['date_naissance']) ? date('d/m/Y', strtotime($userDetails['details']['date_naissance'])) : 'Non renseigné'; ?>" disabled>
                                    </div>
                                    <div class="form-group">
                                        <label>Sexe</label>
                                        <input type="text" class="form-control" value="<?php echo !empty($userDetails['details']['sexe']) ? htmlspecialchars($userDetails['details']['sexe']) : 'Non renseigné'; ?>" disabled>
                                    </div>
                                    <div class="form-group">
                                        <label>Adresse</label>
                                        <input type="text" class="form-control" value="<?php echo !empty($userDetails['details']['adresse']) ? htmlspecialchars($userDetails['details']['adresse']) : 'Non renseigné'; ?>" disabled>
                                    </div>
                                <?php elseif ($userDetails['type'] == 'medecin'): ?>
                                    <h4 class="section-title">Informations Professionnelles</h4>
                                    <div class="form-group">
                                        <label>Spécialité</label>
                                        <input type="text" class="form-control" value="<?php echo !empty($userDetails['details']['spécialité']) ? htmlspecialchars($userDetails['details']['spécialité']) : 'Non renseigné'; ?>" disabled>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($userDetails['type'] == 'patient'): ?>
                            <div class="form-group">
                                <label>Dossier Médical</label>
                                <textarea class="form-control" rows="5" disabled><?php echo !empty($userDetails['details']['dossier_medical']) ? htmlspecialchars($userDetails['details']['dossier_medical']) : 'Aucun dossier médical'; ?></textarea>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($userDetails['type'] == 'patient'): ?>
                        <!-- Appointments Tab -->
                        <div id="rdv-tab" class="tab-content">
                            <h4 class="section-title">Historique des Rendez-vous</h4>
                            <?php
                            $rdvQuery = $conn->query("
                                SELECT r.*, um.nom AS medecin_nom, um.prenom AS medecin_prenom 
                                FROM rendezvous r
                                JOIN traitement t ON r.id_traitement = t.id_traitement
                                JOIN utilisateur um ON t.id_medecin = um.id_utilisateur
                                WHERE t.id_patient = $userId
                                ORDER BY r.date_rdv DESC
                            ");

                            if ($rdvQuery->num_rows > 0): ?>
                                <div class="table-responsive">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Médecin</th>
                                                <th>Motif</th>
                                                <th>Lieu</th>
                                                <th>Statut</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($rdv = $rdvQuery->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo date('d/m/Y', strtotime($rdv['date_rdv'])); ?> à <?php echo date('H:i', strtotime($rdv['heure'])); ?></td>
                                                    <td>Dr. <?php echo htmlspecialchars($rdv['medecin_prenom'] . ' ' . $rdv['medecin_nom']); ?></td>
                                                    <td><?php echo htmlspecialchars($rdv['motif']); ?></td>
                                                    <td><?php echo htmlspecialchars($rdv['lieu']); ?></td>
                                                    <td>
                                                        <span class="status-badge status-<?php echo strtolower($rdv['statut']); ?>">
                                                            <?php echo ucfirst($rdv['statut']); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="no-data">
                                    <i class="fas fa-calendar-times fa-2x" style="margin-bottom: 1rem;"></i>
                                    <p>Aucun rendez-vous trouvé pour ce patient</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Prescriptions Tab -->
                        <div id="ordo-tab" class="tab-content">
                            <h4 class="section-title">Ordonnances Médicales</h4>
                            <?php
                            $ordoQuery = $conn->query("
                                SELECT o.*, um.nom AS medecin_nom, um.prenom AS medecin_prenom 
                                FROM ordonnance o
                                JOIN traitement t ON o.id_traitement = t.id_traitement
                                JOIN utilisateur um ON t.id_medecin = um.id_utilisateur
                                WHERE t.id_patient = $userId
                                ORDER BY o.date_ordonnance DESC
                            ");

                            if ($ordoQuery->num_rows > 0): ?>
                                <div class="table-responsive">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Médecin</th>
                                                <th>Médicaments/Traitement</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($ordo = $ordoQuery->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo date('d/m/Y', strtotime($ordo['date_ordonnance'])); ?></td>
                                                    <td>Dr. <?php echo htmlspecialchars($ordo['medecin_prenom'] . ' ' . $ordo['medecin_nom']); ?></td>
                                                    <td>
                                                        <?php if (strpos($ordo['médicaments'], 'uploads/') === 0): ?>
                                                            <a href="<?php echo htmlspecialchars($ordo['médicaments']); ?>" target="_blank" class="btn btn-sm btn-primary">
                                                                <i class="fas fa-file-pdf"></i> Voir l'ordonnance
                                                            </a>
                                                        <?php else: ?>
                                                            <?php echo nl2br(htmlspecialchars(substr($ordo['médicaments'], 0, 100) . (strlen($ordo['médicaments']) > 100 ? '...' : ''))); ?>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <a href="<?php echo strpos($ordo['médicaments'], 'uploads/') === 0 ? $ordo['médicaments'] : 'data:text/plain;charset=utf-8,' . urlencode($ordo['médicaments']); ?>"
                                                            download="ordonnance_<?php echo $userId; ?>_<?php echo $ordo['date_ordonnance']; ?>.<?php echo strpos($ordo['médicaments'], 'uploads/') === 0 ? pathinfo($ordo['médicaments'], PATHINFO_EXTENSION) : 'txt'; ?>"
                                                            class="action-btn btn-edit">
                                                            <i class="fas fa-download"></i> Télécharger
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="no-data">
                                    <i class="fas fa-prescription-bottle-alt fa-2x" style="margin-bottom: 1rem;"></i>
                                    <p>Aucune ordonnance trouvée pour ce patient</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Hospitalizations Tab -->
                        <div id="hospital-tab" class="tab-content">
                            <h4 class="section-title">Historique des Hospitalisations</h4>
                            <?php
                            $hospQuery = $conn->query("
                                SELECT h.*, um.nom AS medecin_nom, um.prenom AS medecin_prenom 
                                FROM hospitalisation h
                                JOIN traitement t ON h.id_traitement = t.id_traitement
                                JOIN utilisateur um ON t.id_medecin = um.id_utilisateur
                                WHERE h.id_patient = $userId
                                ORDER BY h.date_entree DESC
                            ");

                            if ($hospQuery->num_rows > 0): ?>
                                <div class="table-responsive">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th>Service</th>
                                                <th>Date Entrée</th>
                                                <th>Date Sortie</th>
                                                <th>Médecin</th>
                                                <th>Durée</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($hosp = $hospQuery->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($hosp['service']); ?></td>
                                                    <td><?php echo date('d/m/Y', strtotime($hosp['date_entree'])); ?></td>
                                                    <td><?php echo $hosp['date_sortie'] ? date('d/m/Y', strtotime($hosp['date_sortie'])) : 'En cours'; ?></td>
                                                    <td>Dr. <?php echo htmlspecialchars($hosp['medecin_prenom'] . ' ' . $hosp['medecin_nom']); ?></td>
                                                    <td>
                                                        <?php
                                                        $start = new DateTime($hosp['date_entree']);
                                                        $end = $hosp['date_sortie'] ? new DateTime($hosp['date_sortie']) : new DateTime();
                                                        $interval = $start->diff($end);
                                                        echo $interval->format('%a jours');
                                                        ?>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="no-data">
                                    <i class="fas fa-procedures fa-2x" style="margin-bottom: 1rem;"></i>
                                    <p>Aucune hospitalisation trouvée pour ce patient</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php elseif ($userDetails['type'] == 'medecin'): ?>
                        <!-- Patients Tab -->
                        <div id="patients-tab" class="tab-content">
                            <h4 class="section-title">Patients Suivis</h4>
                            <?php
                            $patientsQuery = $conn->query("
                                SELECT DISTINCT up.*, p.date_naissance, p.sexe, p.adresse
                                FROM utilisateur up
                                JOIN patient p ON up.id_utilisateur = p.id_patient
                                JOIN traitement t ON p.id_patient = t.id_patient
                                WHERE t.id_medecin = $userId
                                ORDER BY up.nom, up.prenom
                            ");

                            if ($patientsQuery->num_rows > 0): ?>
                                <div class="table-responsive">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th>Patient</th>
                                                <th>Date de Naissance</th>
                                                <th>Téléphone</th>
                                                <th>Email</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($patient = $patientsQuery->fetch_assoc()): ?>
                                                <tr>
                                                    <td class="user-cell">
                                                        <img src="patient.png" class="avatar" alt="Patient">
                                                        <div>
                                                            <div class="user-name"><?php echo htmlspecialchars($patient['prenom'] . ' ' . $patient['nom']); ?></div>
                                                        </div>
                                                    </td>
                                                    <td><?php echo date('d/m/Y', strtotime($patient['date_naissance'])); ?></td>
                                                    <td><?php echo htmlspecialchars($patient['telephone']); ?></td>
                                                    <td><?php echo htmlspecialchars($patient['email']); ?></td>
                                                    <td>
                                                        <button class="action-btn btn-view" onclick="window.location.href='?user_id=<?php echo $patient['id_utilisateur']; ?>'">
                                                            <i class="fas fa-eye"></i> Dossier
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="no-data">
                                    <i class="fas fa-user-times fa-2x" style="margin-bottom: 1rem;"></i>
                                    <p>Aucun patient trouvé pour ce médecin</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Consultations Tab -->
                        <div id="consult-tab" class="tab-content">
                            <h4 class="section-title">Historique des Consultations</h4>
                            <?php
                            $consultQuery = $conn->query("
                                SELECT t.*, up.nom AS patient_nom, up.prenom AS patient_prenom 
                                FROM traitement t
                                JOIN utilisateur up ON t.id_patient = up.id_utilisateur
                                WHERE t.id_medecin = $userId
                                ORDER BY t.date_debut DESC
                            ");

                            if ($consultQuery->num_rows > 0): ?>
                                <div class="table-responsive">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th>Patient</th>
                                                <th>Date Début</th>
                                                <th>Date Fin</th>
                                                <th>Diagnostic</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($consult = $consultQuery->fetch_assoc()): ?>
                                                <tr>
                                                    <td class="user-cell">
                                                        <img src="patient.png" class="avatar" alt="Patient">
                                                        <div>
                                                            <div class="user-name"><?php echo htmlspecialchars($consult['patient_prenom'] . ' ' . $consult['patient_nom']); ?></div>
                                                        </div>
                                                    </td>
                                                    <td><?php echo date('d/m/Y', strtotime($consult['date_debut'])); ?></td>
                                                    <td><?php echo $consult['date_fin'] ? date('d/m/Y', strtotime($consult['date_fin'])) : 'En cours'; ?></td>
                                                    <td><?php echo htmlspecialchars($consult['diagnostic'] ?: 'Non spécifié'); ?></td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="no-data">
                                    <i class="fas fa-stethoscope fa-2x" style="margin-bottom: 1rem;"></i>
                                    <p>Aucune consultation trouvée pour ce médecin</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php elseif ($current_view == 'statistics'): ?>
                <div class="form-container fade-in">
                    <h2 class="form-title">Statistiques Médicales</h2>

                    



                    <div class="table-container">
                        <h3>Activité Récente</h3>
                        <table>
                            <thead>
                                <tr>
                                    <th>Métrique</th>
                                    <th>Aujourd'hui</th>
                                    <th>Cette Semaine</th>
                                    <th>Ce Mois</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Rendez-vous (existant) -->
                                <tr>
                                    <td>Rendez-vous Planifiés</td>
                                    <td><?php echo $stats['rdv_aujourdhui']; ?></td>
                                    <td><?php
                                        $result = $conn->query("SELECT COUNT(*) as total FROM rendezvous WHERE YEARWEEK(date_rdv, 1) = YEARWEEK(CURDATE(), 1)");
                                        echo $result ? $result->fetch_assoc()['total'] : 0;
                                        ?></td>
                                    <td><?php
                                        $result = $conn->query("SELECT COUNT(*) as total FROM rendezvous WHERE MONTH(date_rdv) = MONTH(CURDATE()) AND YEAR(date_rdv) = YEAR(CURDATE())");
                                        echo $result ? $result->fetch_assoc()['total'] : 0;
                                        ?></td>
                                    <td><?php
                                        $result = $conn->query("SELECT COUNT(*) as total FROM rendezvous");
                                        echo $result ? $result->fetch_assoc()['total'] : 0;
                                        ?></td>
                                </tr>

                                <!-- Ordonnances (existant) -->
                                <tr>
                                    <td>Ordonnances Créées</td>
                                    <td><?php
                                        $result = $conn->query("SELECT COUNT(*) as total FROM ordonnance WHERE DATE(date_ordonnance) = CURDATE()");
                                        echo $result ? $result->fetch_assoc()['total'] : 0;
                                        ?></td>
                                    <td><?php
                                        $result = $conn->query("SELECT COUNT(*) as total FROM ordonnance WHERE YEARWEEK(date_ordonnance, 1) = YEARWEEK(CURDATE(), 1)");
                                        echo $result ? $result->fetch_assoc()['total'] : 0;
                                        ?></td>
                                    <td><?php echo $stats['ordonnances']; ?></td>
                                    <td><?php
                                        $result = $conn->query("SELECT COUNT(*) as total FROM ordonnance");
                                        echo $result ? $result->fetch_assoc()['total'] : 0;
                                        ?></td>
                                </tr>

                                <!-- Hospitalisations (existant) -->
                                <tr>
                                    <td>Nouvelles Hospitalisations</td>
                                    <td><?php
                                        $result = $conn->query("SELECT COUNT(*) as total FROM hospitalisation WHERE DATE(date_entree) = CURDATE()");
                                        echo $result ? $result->fetch_assoc()['total'] : 0;
                                        ?></td>
                                    <td><?php
                                        $result = $conn->query("SELECT COUNT(*) as total FROM hospitalisation WHERE YEARWEEK(date_entree, 1) = YEARWEEK(CURDATE(), 1)");
                                        echo $result ? $result->fetch_assoc()['total'] : 0;
                                        ?></td>
                                    <td><?php
                                        $result = $conn->query("SELECT COUNT(*) as total FROM hospitalisation WHERE MONTH(date_entree) = MONTH(CURDATE()) AND YEAR(date_entree) = YEAR(CURDATE())");
                                        echo $result ? $result->fetch_assoc()['total'] : 0;
                                        ?></td>
                                    <td><?php
                                        $result = $conn->query("SELECT COUNT(*) as total FROM hospitalisation");
                                        echo $result ? $result->fetch_assoc()['total'] : 0;
                                        ?></td>
                                </tr>

                                <!-- Nouveaux Patients (existant) -->
                                <tr>
                                    <td>Nouveaux Patients Inscrits</td>
                                    <td><?php
                                        $result = $conn->query("SELECT COUNT(*) as total FROM utilisateur WHERE rôle = 'patient' AND DATE(date_creation) = CURDATE()");
                                        echo $result ? $result->fetch_assoc()['total'] : 0;
                                        ?></td>
                                    <td><?php
                                        $result = $conn->query("SELECT COUNT(*) as total FROM utilisateur WHERE rôle = 'patient' AND YEARWEEK(date_creation, 1) = YEARWEEK(CURDATE(), 1)");
                                        echo $result ? $result->fetch_assoc()['total'] : 0;
                                        ?></td>
                                    <td><?php
                                        $result = $conn->query("SELECT COUNT(*) as total FROM utilisateur WHERE rôle = 'patient' AND MONTH(date_creation) = MONTH(CURDATE()) AND YEAR(date_creation) = YEAR(CURDATE())");
                                        echo $result ? $result->fetch_assoc()['total'] : 0;
                                        ?></td>
                                    <td><?php echo $stats['patients']; ?></td>
                                </tr>

                                <!-- NOUVEAU: Consultations Terminées -->
                                <tr>
                                    <td>Consultations Terminées</td>
                                    <td><?php
                                        $result = $conn->query("SELECT COUNT(*) as total FROM traitement WHERE date_fin IS NOT NULL AND DATE(date_fin) = CURDATE()");
                                        echo $result ? $result->fetch_assoc()['total'] : 0;
                                        ?></td>
                                    <td><?php
                                        $result = $conn->query("SELECT COUNT(*) as total FROM traitement WHERE date_fin IS NOT NULL AND YEARWEEK(date_fin, 1) = YEARWEEK(CURDATE(), 1)");
                                        echo $result ? $result->fetch_assoc()['total'] : 0;
                                        ?></td>
                                    <td><?php
                                        $result = $conn->query("SELECT COUNT(*) as total FROM traitement WHERE date_fin IS NOT NULL AND MONTH(date_fin) = MONTH(CURDATE()) AND YEAR(date_fin) = YEAR(CURDATE())");
                                        echo $result ? $result->fetch_assoc()['total'] : 0;
                                        ?></td>
                                    <td><?php
                                        $result = $conn->query("SELECT COUNT(*) as total FROM traitement WHERE date_fin IS NOT NULL");
                                        echo $result ? $result->fetch_assoc()['total'] : 0;
                                        ?></td>
                                </tr>

                                <!-- NOUVEAU: Rendez-vous Annulés -->
                                <tr>
                                    <td>Rendez-vous Annulés</td>
                                    <td><?php
                                        // On suppose que l'annulation est enregistrée le jour même
                                        $result = $conn->query("SELECT COUNT(*) as total FROM rendezvous WHERE statut = 'annulé' AND DATE(date_rdv) = CURDATE()");
                                        echo $result ? $result->fetch_assoc()['total'] : 0;
                                        ?></td>
                                    <td><?php
                                        $result = $conn->query("SELECT COUNT(*) as total FROM rendezvous WHERE statut = 'annulé' AND YEARWEEK(date_rdv, 1) = YEARWEEK(CURDATE(), 1)");
                                        echo $result ? $result->fetch_assoc()['total'] : 0;
                                        ?></td>
                                    <td><?php
                                        $result = $conn->query("SELECT COUNT(*) as total FROM rendezvous WHERE statut = 'annulé' AND MONTH(date_rdv) = MONTH(CURDATE()) AND YEAR(date_rdv) = YEAR(CURDATE())");
                                        echo $result ? $result->fetch_assoc()['total'] : 0;
                                        ?></td>
                                    <td><?php
                                        $result = $conn->query("SELECT COUNT(*) as total FROM rendezvous WHERE statut = 'annulé'");
                                        echo $result ? $result->fetch_assoc()['total'] : 0;
                                        ?></td>
                                </tr>
                            </tbody>

                        </table>
                    </div>

                </div>
            <?php elseif ($current_view == 'settings'): ?>
                <div class="form-container fade-in">
                    <h2 class="form-title">Paramètres du Système</h2>

                    <div class="settings-section">
                        <h3>Paramètres Généraux</h3>

                        <div class="form-group">
                            <label>Nom du Cabinet</label>
                            <input type="text" class="form-control" value="Cabinet Médical" placeholder="Entrez le nom de votre cabinet">
                        </div>

                        <div class="form-group">
                            <label>Adresse du Cabinet</label>
                            <textarea class="form-control" rows="3" placeholder="Entrez l'adresse complète"></textarea>
                        </div>

                        <div class="form-group">
                            <label>Téléphone du Cabinet</label>
                            <input type="tel" class="form-control" placeholder="Numéro de téléphone principal">
                        </div>
                    </div>

                    <div class="settings-section">
                        <h3>Paramètres de Notification</h3>

                        <div class="settings-option">
                            <div>
                                <strong>Notifications Email</strong>
                                <p>Activer les notifications par email</p>
                            </div>
                            <label class="switch">
                                <input type="checkbox" checked>
                                <span class="slider"></span>
                            </label>
                        </div>

                        <div class="settings-option">
                            <div>
                                <strong>Rappels SMS</strong>
                                <p>Envoyer des SMS pour les rappels de RDV</p>
                            </div>
                            <label class="switch">
                                <input type="checkbox">
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>

                    <div class="settings-section">
                        <h3>Sécurité</h3>

                        <div class="settings-option">
                            <div>
                                <strong>Authentification à deux facteurs</strong>



                                <p>Exiger une vérification supplémentaire pour les connexions</p>
                            </div>
                            <label class="switch">
                                <input type="checkbox">
                                <span class="slider"></span>
                            </label>
                        </div>

                        <div class="form-group">
                            <label>Durée de session (minutes)</label>
                            <input type="number" class="form-control" value="30" min="5" max="1440">
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline">Annuler</button>
                        <button type="button" class="btn btn-primary" id="saveSettingsBtn">Enregistrer les modifications</button>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Profile Modal -->
    <div class="modal" id="profileModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Mon Profil</h3>
                <button class="modal-close" id="closeProfileModal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="profileForm" method="post" enctype="multipart/form-data">
                    <div class="profile-picture-container">
                        <img src="<?php echo isset($_SESSION['profile_picture']) ? $_SESSION['profile_picture'] : 'img.jpg'; ?>" class="profile-picture" id="profilePicturePreview">
                        <div class="profile-picture-upload">
                            <input type="file" name="profile_picture" id="profilePictureInput" accept="image/*">
                            <small>Format: JPG, PNG (Max 2MB)</small>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label>Nom</label>
                                <input type="text" name="nom" class="form-control" value="<?php echo htmlspecialchars(explode(' ', $_SESSION['ad_name'] ?? '')[1] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label>Prénom</label>
                                <input type="text" name="prenom" class="form-control" value="<?php echo htmlspecialchars(explode(' ', $_SESSION['ad_name'] ?? '')[0] ?? ''); ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Téléphone</label>
                        <input type="tel" name="telephone" class="form-control" value="<?php echo htmlspecialchars($_SESSION['telephone'] ?? ''); ?>">
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" id="cancelProfileBtn">Annuler</button>
                        <button type="submit" name="update_profile" class="btn btn-primary">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Change Password Modal -->
    <div class="modal" id="passwordModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Changer le Mot de Passe</h3>
                <button class="modal-close" id="closePasswordModal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="passwordForm" method="post">
                    <div class="form-group">
                        <label>Mot de Passe Actuel</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label>Nouveau Mot de Passe</label>
                        <input type="password" name="new_password" class="form-control" required minlength="8">
                    </div>

                    <div class="form-group">
                        <label>Confirmer le Nouveau Mot de Passe</label>
                        <input type="password" name="confirm_password" class="form-control" required minlength="8">
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" id="cancelPasswordBtn">Annuler</button>
                        <button type="submit" name="change_password" class="btn btn-primary">Changer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Patient Modal -->
    <div class="modal" id="addPatientModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Ajouter un Nouveau Patient</h3>
                <button class="modal-close" id="closePatientModal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="patientForm" method="post">
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label>Nom</label>
                                <input type="text" name="nom" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label>Prénom</label>
                                <input type="text" name="prenom" class="form-control" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label>Téléphone</label>
                        <input type="tel" name="telephone" class="form-control">
                    </div>

                    <div class="form-group">
                        <label>Date de Naissance</label>
                        <input type="date" name="date_naissance" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label>Sexe</label>
                        <select name="sexe" class="form-control" required>
                            <option value="M">Masculin</option>
                            <option value="F">Féminin</option>
                            <option value="A">Autre</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Adresse</label>
                        <textarea name="adresse" class="form-control" rows="3"></textarea>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" id="cancelPatientBtn">Annuler</button>
                        <button type="submit" name="add_patient" class="btn btn-primary">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div class="modal" id="addUserModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Ajouter un Nouvel Utilisateur</h3>
                <button class="modal-close" id="closeUserModal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="userForm" method="post">
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label>Nom</label>
                                <input type="text" name="nom" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label>Prénom</label>
                                <input type="text" name="prenom" class="form-control" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label>Téléphone</label>
                        <input type="tel" name="telephone" class="form-control">
                    </div>

                    <div class="form-group">
                        <label>Rôle</label>
                        <select name="role" class="form-control" required onchange="toggleSpecialite(this.value)">
                            <option value="assistant">Assistant</option>
                            <option value="medecin">Médecin</option>
                        </select>
                    </div>

                    <div class="form-group" id="specialiteGroup" style="display: none;">
                        <label>Spécialité</label>
                        <input type="text" name="specialite" class="form-control" placeholder="Ex: Cardiologie, Pédiatrie...">
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" id="cancelUserBtn">Annuler</button>
                        <button type="submit" name="add_user" class="btn btn-primary">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Appointment Modal -->
    <div class="modal" id="addAppointmentModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Nouveau Rendez-vous</h3>
                <button class="modal-close" id="closeAppointmentModal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="appointmentForm" method="post">
                    <div class="form-group">
                        <label>Patient</label>
                        <select name="patient_id" class="form-control" required>
                            <option value="">Sélectionner un patient</option>
                            <?php
                            $patients = $conn->query("SELECT up.id_utilisateur, up.nom, up.prenom FROM utilisateur up JOIN patient p ON up.id_utilisateur = p.id_patient ORDER BY up.nom, up.prenom");
                            while ($patient = $patients->fetch_assoc()): ?>
                                <option value="<?php echo $patient['id_utilisateur']; ?>"><?php echo htmlspecialchars($patient['prenom'] . ' ' . $patient['nom']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Médecin</label>
                        <select name="medecin_id" class="form-control" required>
                            <option value="">Sélectionner un médecin</option>
                            <?php
                            $medecins = $conn->query("SELECT um.id_utilisateur, um.nom, um.prenom FROM utilisateur um JOIN medecin m ON um.id_utilisateur = m.id_medecin ORDER BY um.nom, um.prenom");
                            while ($medecin = $medecins->fetch_assoc()): ?>
                                <option value="<?php echo $medecin['id_utilisateur']; ?>">Dr. <?php echo htmlspecialchars($medecin['prenom'] . ' ' . $medecin['nom']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label>Date</label>
                                <input type="date" name="date_rdv" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label>Heure</label>
                                <input type="time" name="heure" class="form-control" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Motif</label>
                        <input type="text" name="motif" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label>Lieu</label>
                        <input type="text" name="lieu" class="form-control" required>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" id="cancelAppointmentBtn">Annuler</button>
                        <button type="submit" name="add_appointment" class="btn btn-primary">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Prescription Modal -->
    <div class="modal" id="addPrescriptionModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Nouvelle Ordonnance</h3>
                <button class="modal-close" id="closePrescriptionModal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="prescriptionForm" method="post">
                    <div class="form-group">
                        <label>Patient</label>
                        <select name="patient_id" class="form-control" required>
                            <option value="">Sélectionner un patient</option>
                            <?php
                            $patients = $conn->query("SELECT up.id_utilisateur, up.nom, up.prenom FROM utilisateur up JOIN patient p ON up.id_utilisateur = p.id_patient ORDER BY up.nom, up.prenom");
                            while ($patient = $patients->fetch_assoc()): ?>
                                <option value="<?php echo $patient['id_utilisateur']; ?>"><?php echo htmlspecialchars($patient['prenom'] . ' ' . $patient['nom']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Médecin</label>
                        <select name="medecin_id" class="form-control" required>
                            <option value="">Sélectionner un médecin</option>
                            <?php
                            $medecins = $conn->query("SELECT um.id_utilisateur, um.nom, um.prenom FROM utilisateur um JOIN medecin m ON um.id_utilisateur = m.id_medecin ORDER BY um.nom, um.prenom");
                            while ($medecin = $medecins->fetch_assoc()): ?>
                                <option value="<?php echo $medecin['id_utilisateur']; ?>">Dr. <?php echo htmlspecialchars($medecin['prenom'] . ' ' . $medecin['nom']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Date de l'ordonnance</label>
                        <input type="date" name="date_ordonnance" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Médicaments et Traitement</label>
                        <textarea name="medicaments" class="form-control" rows="5" placeholder="Détaillez les médicaments, posologie et instructions..." required></textarea>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" id="cancelPrescriptionBtn">Annuler</button>
                        <button type="submit" name="add_prescription" class="btn btn-primary">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <!-- Edit User Modal -->
    <div class="modal" id="editUserModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Modifier les Informations</h3>
                <button class="modal-close" data-modal-close="editUserModal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editUserForm" method="post">
                    <input type="hidden" name="user_id" id="editUserId">

                    <!-- ... (champs nom, prenom, email, telephone restent identiques) ... -->
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label>Nom</label>
                                <input type="text" name="nom" id="editUserNom" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label>Prénom</label>
                                <input type="text" name="prenom" id="editUserPrenom" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" id="editUserEmail" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Téléphone</label>
                        <input type="tel" name="telephone" id="editUserTelephone" class="form-control">
                    </div>

                    <!-- DÉBUT DE LA MODIFICATION : Groupe pour le Rôle (sera caché pour les patients) -->
                    <div class="form-group" id="editRoleGroup">
                        <label>Rôle</label>
                        <select name="role" id="editUserRole" class="form-control" required onchange="toggleEditFields(this.value)">
                            <option value="patient">Patient</option>
                            <option value="assistant">Assistant</option>
                            <option value="medecin">Médecin</option>
                        </select>
                    </div>
                    <!-- FIN DE LA MODIFICATION -->

                    <!-- DÉBUT DE LA MODIFICATION : Groupe pour le Statut (sera affiché pour les patients) -->
                    <div class="form-group" id="editStatutGroup" style="display: none;">
                        <label>Statut</label>
                        <input type="text" id="editUserStatut" class="form-control" disabled>
                    </div>
                    <!-- FIN DE LA MODIFICATION -->

                    <!-- Champs spécifiques au médecin -->
                    <div class="form-group" id="editSpecialiteGroup" style="display: none;">
                        <label>Spécialité</label>
                        <input type="text" name="specialite" id="editUserSpecialite" class="form-control" placeholder="Ex: Cardiologie, Pédiatrie...">
                    </div>

                    <!-- Champs spécifiques au patient -->
                    <div id="editPatientFields" style="display: none;">
                        <div class="form-group">
                            <label>Date de Naissance</label>
                            <input type="date" name="date_naissance" id="editUserDateNaissance" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Sexe</label>
                            <select name="sexe" id="editUserSexe" class="form-control">
                                <option value="M">Masculin</option>
                                <option value="F">Féminin</option>
                                <option value="A">Autre</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Adresse</label>
                            <textarea name="adresse" id="editUserAdresse" class="form-control" rows="3"></textarea>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" data-modal-close="editUserModal">Annuler</button>
                        <button type="submit" name="edit_user" class="btn btn-primary">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Appointment Modal -->
    <div class="modal" id="editAppointmentModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Modifier le Rendez-vous</h3>
                <button class="modal-close" id="closeEditAppointmentModal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editAppointmentForm" method="post">
                    <input type="hidden" name="rdv_id" id="editRdvId">

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label>Date</label>
                                <input type="date" name="date_rdv" id="editRdvDate" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label>Heure</label>
                                <input type="time" name="heure" id="editRdvHeure" class="form-control" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Motif</label>
                        <input type="text" name="motif" id="editRdvMotif" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label>Lieu</label>
                        <input type="text" name="lieu" id="editRdvLieu" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label>Statut</label>
                        <select name="statut" id="editRdvStatut" class="form-control" required>
                            <option value="confirmé">Confirmé</option>
                            <option value="en_attente">En attente</option>
                            <option value="annulé">Annulé</option>
                        </select>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" id="cancelEditAppointmentBtn">Annuler</button>
                        <button type="submit" name="edit_appointment" class="btn btn-primary">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modale pour Ajouter un Nouveau Patient -->
    <div id="addPatientModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Ajouter un Nouveau Patient</h3>
                <button class="modal-close" data-modal-close="addPatientModal">&times;</button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <!-- Contenu du formulaire d'ajout de patient -->
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group"><label>Nom</label><input type="text" name="nom" class="form-control" required></div>
                        </div>
                        <div class="form-col">
                            <div class="form-group"><label>Prénom</label><input type="text" name="prenom" class="form-control" required></div>
                        </div>
                    </div>
                    <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control" required></div>
                    <div class="form-group"><label>Téléphone</label><input type="tel" name="telephone" class="form-control"></div>
                    <div class="form-group"><label>Date de Naissance</label><input type="date" name="date_naissance" class="form-control" required></div>
                    <div class="form-group"><label>Sexe</label><select name="sexe" class="form-control" required>
                            <option value="M">Masculin</option>
                            <option value="F">Féminin</option>
                            <option value="A">Autre</option>
                        </select></div>
                    <div class="form-group"><label>Adresse</label><textarea name="adresse" class="form-control" rows="3"></textarea></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" data-modal-close="addPatientModal">Annuler</button>
                    <button type="submit" name="add_patient" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modale pour Ajouter un Nouvel Utilisateur -->
    <div id="addUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Ajouter un Nouvel Utilisateur</h3>
                <button class="modal-close" data-modal-close="addUserModal">&times;</button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <!-- Contenu du formulaire d'ajout d'utilisateur -->
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group"><label>Nom</label><input type="text" name="nom" class="form-control" required></div>
                        </div>
                        <div class="form-col">
                            <div class="form-group"><label>Prénom</label><input type="text" name="prenom" class="form-control" required></div>
                        </div>
                    </div>
                    <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control" required></div>
                    <div class="form-group"><label>Téléphone</label><input type="tel" name="telephone" class="form-control"></div>
                    <div class="form-group"><label>Rôle</label><select name="role" class="form-control" required onchange="toggleSpecialite(this.value, 'specialiteGroupModal')">
                            <option value="assistant">Assistant</option>
                            <option value="medecin">Médecin</option>
                        </select></div>
                    <div class="form-group" id="specialiteGroupModal" style="display: none;"><label>Spécialité</label><input type="text" name="specialite" class="form-control" placeholder="Ex: Cardiologie, Pédiatrie..."></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" data-modal-close="addUserModal">Annuler</button>
                    <button type="submit" name="add_user" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modale pour Ajouter un Nouveau RDV -->
    <div id="addAppointmentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Nouveau Rendez-vous</h3>
                <button class="modal-close" data-modal-close="addAppointmentModal">&times;</button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Patient</label>
                        <select name="patient_id" class="form-control" required>
                            <option value="">Sélectionner un patient</option>
                            <?php foreach ($patients_list as $patient): ?>
                                <option value="<?php echo $patient['id_utilisateur']; ?>"><?php echo htmlspecialchars($patient['prenom'] . ' ' . $patient['nom']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Médecin</label>
                        <select name="medecin_id" class="form-control" required>
                            <option value="">Sélectionner un médecin</option>
                            <?php foreach ($medecins_list as $medecin): ?>
                                <option value="<?php echo $medecin['id_utilisateur']; ?>">Dr. <?php echo htmlspecialchars($medecin['prenom'] . ' ' . $medecin['nom']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group"><label>Date</label><input type="date" name="date_rdv" class="form-control" required></div>
                        </div>
                        <div class="form-col">
                            <div class="form-group"><label>Heure</label><input type="time" name="heure" class="form-control" required></div>
                        </div>
                    </div>
                    <div class="form-group"><label>Motif</label><input type="text" name="motif" class="form-control" required></div>
                    <div class="form-group"><label>Lieu</label><input type="text" name="lieu" class="form-control" required></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" data-modal-close="addAppointmentModal">Annuler</button>
                    <button type="submit" name="add_appointment" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>





    <!-- Modale pour Modifier un Utilisateur -->
    <div class="modal" id="editUserModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Modifier l'Utilisateur</h3>
                <button class="modal-close" data-modal-close="editUserModal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editUserForm" method="post">
                    <input type="hidden" name="user_id" id="editUserId">

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label>Nom</label>
                                <input type="text" name="nom" id="editUserNom" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label>Prénom</label>
                                <input type="text" name="prenom" id="editUserPrenom" class="form-control" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" id="editUserEmail" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label>Téléphone</label>
                        <input type="tel" name="telephone" id="editUserTelephone" class="form-control">
                    </div>

                    <div class="form-group">
                        <label>Rôle</label>
                        <select name="role" id="editUserRole" class="form-control" required onchange="toggleEditSpecialite(this.value)">
                            <option value="patient">Patient</option>
                            <option value="assistant">Assistant</option>
                            <option value="medecin">Médecin</option>
                        </select>
                    </div>

                    <div class="form-group" id="editSpecialiteGroup" style="display: none;">
                        <label>Spécialité</label>
                        <input type="text" name="specialite" id="editUserSpecialite" class="form-control" placeholder="Ex: Cardiologie, Pédiatrie...">
                    </div>

                    <div id="editPatientFields" style="display: none;">
                        <div class="form-group">
                            <label>Date de Naissance</label>
                            <input type="date" name="date_naissance" id="editUserDateNaissance" class="form-control">
                        </div>

                        <div class="form-group">
                            <label>Sexe</label>
                            <select name="sexe" id="editUserSexe" class="form-control">
                                <option value="M">Masculin</option>
                                <option value="F">Féminin</option>
                                <option value="A">Autre</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Adresse</label>
                            <textarea name="adresse" id="editUserAdresse" class="form-control" rows="3"></textarea>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" data-modal-close="editUserModal">Annuler</button>
                        <button type="submit" name="edit_user" class="btn btn-primary">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Modale pour Modifier un Rendez-vous -->
    <div id="editAppointmentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Modifier le Rendez-vous</h3>
                <button type="button" class="modal-close" data-modal-close="editAppointmentModal">&times;</button>
            </div>
            <form id="editAppointmentForm" method="post">
                <div class="modal-body">
                    <input type="hidden" name="rdv_id" id="editRdvId">
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group"><label>Date</label><input type="date" name="date_rdv" id="editRdvDate" class="form-control" required></div>
                        </div>
                        <div class="form-col">
                            <div class="form-group"><label>Heure</label><input type="time" name="heure" id="editRdvHeure" class="form-control" required></div>
                        </div>
                    </div>
                    <div class="form-group"><label>Motif</label><input type="text" name="motif" id="editRdvMotif" class="form-control" required></div>
                    <div class="form-group"><label>Lieu</label><input type="text" name="lieu" id="editRdvLieu" class="form-control" required></div>
                    <div class="form-group"><label>Statut</label><select name="statut" id="editRdvStatut" class="form-control" required>
                            <option value="confirmé">Confirmé</option>
                            <option value="en_attente">En attente</option>
                            <option value="annulé">Annulé</option>
                        </select></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" data-modal-close="editAppointmentModal">Annuler</button>
                    <button type="submit" name="edit_appointment" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ================================================================= -->
    <!-- MODALE UNIVERSELLE POUR AJOUTER/MODIFIER UNE HOSPITALISATION      -->
    <!-- ================================================================= -->
    <div class="modal" id="hospitalizationModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="hospitalizationModalTitle">Nouvelle Hospitalisation</h3>
                <button class="modal-close" data-modal-close="hospitalizationModal">&times;</button>
            </div>
            <form id="hospitalizationForm" method="post">
                <div class="modal-body">
                    <!-- Champ caché pour l'ID en mode modification -->
                    <input type="hidden" name="hospitalisation_id" id="hospitalisationId">

                    <!-- Champ pour l'action (ajout ou modification) -->
                    <input type="hidden" name="action" id="hospitalizationAction" value="add_hospitalization">

                    <div class="form-group">
                        <label>Patient</label>
                        <select name="patient_id" id="hospPatientId" class="form-control" required>
                            <option value="">Sélectionner un patient</option>
                            <?php foreach ($patients_list as $patient): ?>
                                <option value="<?php echo $patient['id_utilisateur']; ?>">
                                    <?php echo htmlspecialchars($patient['prenom'] . ' ' . $patient['nom']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Médecin Référent</label>
                        <select name="medecin_id" id="hospMedecinId" class="form-control" required>
                            <option value="">Sélectionner un médecin</option>
                            <?php foreach ($medecins_list as $medecin): ?>
                                <option value="<?php echo $medecin['id_utilisateur']; ?>">
                                    Dr. <?php echo htmlspecialchars($medecin['prenom'] . ' ' . $medecin['nom']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Service</label>
                        <input type="text" name="service" id="hospService" class="form-control" placeholder="Ex: Cardiologie, Urgences..." required>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label>Date d'Entrée</label>
                                <input type="date" name="date_entree" id="hospDateEntree" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label>Date de Sortie (laisser vide si en cours)</label>
                                <input type="date" name="date_sortie" id="hospDateSortie" class="form-control">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" data-modal-close="hospitalizationModal">Annuler</button>
                    <button type="submit" id="hospitalizationSubmitBtn" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ================================================================= -->
    <!-- MODALE POUR AJOUTER/MODIFIER UNE HOSPITALISATION                  -->
    <!-- ================================================================= -->
    <!-- ================================================================= -->
    <!-- MODALE UNIVERSELLE POUR AJOUTER/MODIFIER UNE HOSPITALISATION      -->
    <!-- ================================================================= -->

    <!-- ================================================================= -->
    <!-- MODALE UNIVERSELLE POUR AJOUTER/MODIFIER UNE HOSPITALISATION      -->
    <!-- ================================================================= -->
    <!-- ================================================================= -->
    <!-- MODALE UNIVERSELLE POUR AJOUTER/MODIFIER UNE HOSPITALISATION (CORRIGÉE) -->
    <!-- ================================================================= -->
    <!-- ================================================================= -->
    <!-- MODALE UNIVERSELLE POUR AJOUTER/MODIFIER UNE HOSPITALISATION      -->
    <!-- ================================================================= -->

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // ... (votre code existant pour SweetAlert, les modales, etc.)

            // --- GESTION DE LA MODALE D'HOSPITALISATION ---

            // Bouton "Nouvelle Hospitalisation"
            const addHospBtn = document.getElementById('addHospitalizationBtn');
            if (addHospBtn) {
                addHospBtn.addEventListener('click', () => {
                    const form = document.getElementById('hospitalizationForm');
                    form.reset(); // Vider le formulaire

                    document.getElementById('hospitalizationModalTitle').textContent = 'Nouvelle Hospitalisation';
                    document.getElementById('hospitalizationSubmitBtn').textContent = 'Enregistrer';

                    // Configurer pour l'ajout
                    const actionInput = document.getElementById('hospitalizationAction');
                    actionInput.name = 'add_hospitalization';
                    actionInput.value = '1';

                    // Rendre les champs patient/médecin modifiables
                    document.getElementById('hospPatientId').disabled = false;
                    document.getElementById('hospMedecinId').disabled = false;
                    document.getElementById('hospDateEntree').readOnly = false;

                    openModal('hospitalizationModal');
                });
            }

            // Logique pour les boutons "Modifier" du tableau
            document.querySelectorAll('[data-modal-open="hospitalizationModal"]').forEach(trigger => {
                // On vérifie que ce n'est pas le bouton d'ajout général
                if (trigger.id !== 'addHospitalizationBtn') {
                    trigger.addEventListener('click', (event) => {
                        event.preventDefault();
                        const dataset = trigger.dataset;

                        // Remplir le formulaire avec les données du bouton
                        document.getElementById('hospitalizationModalTitle').textContent = 'Modifier l\'Hospitalisation';
                        document.getElementById('hospitalizationSubmitBtn').textContent = 'Mettre à jour';

                        document.getElementById('hospitalisationId').value = dataset.hospId;
                        document.getElementById('hospPatientId').value = dataset.patientId;
                        document.getElementById('hospMedecinId').value = dataset.medecinId;
                        document.getElementById('hospService').value = dataset.service;
                        document.getElementById('hospDateEntree').value = dataset.dateEntree;
                        document.getElementById('hospDateSortie').value = dataset.dateSortie;

                        // Configurer pour la modification
                        const actionInput = document.getElementById('hospitalizationAction');
                        actionInput.name = 'edit_hospitalization';
                        actionInput.value = '1';

                        // On ne peut pas changer le patient, le médecin ou la date d'entrée d'une hospitalisation existante
                        document.getElementById('hospPatientId').disabled = true;
                        document.getElementById('hospMedecinId').disabled = true;
                        document.getElementById('hospDateEntree').readOnly = true;

                        openModal('hospitalizationModal');
                    });
                }
            });

            // Soumission du formulaire (pour réactiver les champs désactivés avant envoi)
            const hospForm = document.getElementById('hospitalizationForm');
            if (hospForm) {
                hospForm.addEventListener('submit', () => {
                    document.getElementById('hospPatientId').disabled = false;
                    document.getElementById('hospMedecinId').disabled = false;
                });
            }

            // Fonction pour la sortie rapide du patient
            window.dischargePatient = function(hospId) {
                Swal.fire({
                    title: 'Confirmer la sortie du patient ?',
                    text: "La date de sortie sera enregistrée à aujourd'hui.",
                    icon: 'info',
                    showCancelButton: true,
                    confirmButtonColor: '#28a745',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Oui, confirmer la sortie',
                    cancelButtonText: 'Annuler'
                }).then((result) => {
                    if (result.isConfirmed) {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.style.display = 'none';
                        form.action = 'dashboard_administrateur.php?view=hospitalized';
                        form.innerHTML = `<input type="hidden" name="discharge_patient" value="1">
                                  <input type="hidden" name="hospitalisation_id" value="${hospId}">`;
                        document.body.appendChild(form);
                        form.submit();
                    }
                });
            }

            // ... (le reste de votre code JavaScript)
        });
    </script>

    <script>
        // Dropdown Menu
        document.getElementById('userDropdownBtn').addEventListener('click', function() {
            document.getElementById('userDropdownMenu').classList.toggle('show');
        });

        document.getElementById('profileDropdownBtn').addEventListener('click', function() {
            document.getElementById('profileModal').classList.add('show');
        });

        // Close dropdown when clicking outside
        window.addEventListener('click', function(event) {
            if (!event.target.matches('#userDropdownBtn') && !event.target.closest('#userDropdownMenu')) {
                var dropdowns = document.getElementsByClassName('dropdown-menu');
                for (var i = 0; i < dropdowns.length; i++) {
                    var openDropdown = dropdowns[i];
                    if (openDropdown.classList.contains('show')) {
                        openDropdown.classList.remove('show');
                    }
                }
            }
        });

        // Profile Modal
        document.getElementById('profileBtn').addEventListener('click', function(e) {
            e.preventDefault();
            document.getElementById('userDropdownMenu').classList.remove('show');
            document.getElementById('profileModal').classList.add('show');
        });

        document.getElementById('closeProfileModal').addEventListener('click', function() {
            document.getElementById('profileModal').classList.remove('show');
        });

        document.getElementById('cancelProfileBtn').addEventListener('click', function() {
            document.getElementById('profileModal').classList.remove('show');
        });

        // Password Modal
        document.getElementById('changePasswordBtn').addEventListener('click', function(e) {
            e.preventDefault();
            document.getElementById('userDropdownMenu').classList.remove('show');
            document.getElementById('passwordModal').classList.add('show');
        });

        document.getElementById('closePasswordModal').addEventListener('click', function() {
            document.getElementById('passwordModal').classList.remove('show');
        });

        document.getElementById('cancelPasswordBtn').addEventListener('click', function() {
            document.getElementById('passwordModal').classList.remove('show');
        });

        // Patient Modal
        document.getElementById('addPatientBtn').addEventListener('click', function(e) {
            e.preventDefault();
            document.getElementById('addPatientModal').classList.add('show');
        });

        document.getElementById('closePatientModal').addEventListener('click', function() {
            document.getElementById('addPatientModal').classList.remove('show');
        });

        document.getElementById('cancelPatientBtn').addEventListener('click', function() {
            document.getElementById('addPatientModal').classList.remove('show');
        });

        // User Modal
        document.getElementById('addUserBtn').addEventListener('click', function(e) {
            e.preventDefault();
            document.getElementById('addUserModal').classList.add('show');
        });

        document.getElementById('closeUserModal').addEventListener('click', function() {
            document.getElementById('addUserModal').classList.remove('show');
        });

        document.getElementById('cancelUserBtn').addEventListener('click', function() {
            document.getElementById('addUserModal').classList.remove('show');
        });

        // Appointment Modal
        document.getElementById('addAppointmentBtn').addEventListener('click', function(e) {
            e.preventDefault();
            document.getElementById('addAppointmentModal').classList.add('show');
        });

        document.getElementById('closeAppointmentModal').addEventListener('click', function() {
            document.getElementById('addAppointmentModal').classList.remove('show');
        });

        document.getElementById('cancelAppointmentBtn').addEventListener('click', function() {
            document.getElementById('addAppointmentModal').classList.remove('show');
        });

        // Prescription Modal
        document.getElementById('addPrescriptionBtn').addEventListener('click', function(e) {
            e.preventDefault();
            document.getElementById('addPrescriptionModal').classList.add('show');
        });

        document.getElementById('closePrescriptionModal').addEventListener('click', function() {
            document.getElementById('addPrescriptionModal').classList.remove('show');
        });

        document.getElementById('cancelPrescriptionBtn').addEventListener('click', function() {
            document.getElementById('addPrescriptionModal').classList.remove('show');
        });

        // Edit User Modal
        document.getElementById('closeEditUserModal').addEventListener('click', function() {
            document.getElementById('editUserModal').classList.remove('show');
        });

        document.getElementById('cancelEditUserBtn').addEventListener('click', function() {
            document.getElementById('editUserModal').classList.remove('show');
        });

        // Edit Appointment Modal
        document.getElementById('closeEditAppointmentModal').addEventListener('click', function() {
            document.getElementById('editAppointmentModal').classList.remove('show');
        });

        document.getElementById('cancelEditAppointmentBtn').addEventListener('click', function() {
            document.getElementById('editAppointmentModal').classList.remove('show');
        });

        // Profile Picture Preview
        document.getElementById('profilePictureInput').addEventListener('change', function(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('profilePicturePreview').src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        });

        // Tabs
        function openTab(evt, tabName) {
            const tabContents = document.getElementsByClassName('tab-content');
            for (let i = 0; i < tabContents.length; i++) {
                tabContents[i].classList.remove('active');
            }

            const tabButtons = document.getElementsByClassName('tab-btn');
            for (let i = 0; i < tabButtons.length; i++) {
                tabButtons[i].classList.remove('active');
            }

            document.getElementById(tabName).classList.add('active');
            evt.currentTarget.classList.add('active');
        }

        // Toggle Specialite field
        function toggleSpecialite(role) {
            const specialiteGroup = document.getElementById('specialiteGroup');
            if (role === 'medecin') {
                specialiteGroup.style.display = 'block';
                document.querySelector('#specialiteGroup input').required = true;
            } else {
                specialiteGroup.style.display = 'none';
                document.querySelector('#specialiteGroup input').required = false;
            }
        }

        function toggleEditSpecialite(role) {
            const specialiteGroup = document.getElementById('editSpecialiteGroup');
            const patientFields = document.getElementById('editPatientFields');

            if (role === 'medecin') {
                specialiteGroup.style.display = 'block';
                patientFields.style.display = 'none';
            } else if (role === 'patient') {
                specialiteGroup.style.display = 'none';
                patientFields.style.display = 'block';
            } else {
                specialiteGroup.style.display = 'none';
                patientFields.style.display = 'none';
            }
        }

        // Edit User Function
        function editUser(userId) {
            // Fetch user data via AJAX or use existing data
            fetch(`get_user_data.php?id=${userId}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('editUserId').value = data.id;
                    document.getElementById('editUserNom').value = data.nom;
                    document.getElementById('editUserPrenom').value = data.prenom;
                    document.getElementById('editUserEmail').value = data.email;
                    document.getElementById('editUserTelephone').value = data.telephone;
                    document.getElementById('editUserRole').value = data.role;

                    if (data.role === 'medecin' && data.specialite) {
                        document.getElementById('editUserSpecialite').value = data.specialite;
                    }

                    if (data.role === 'patient') {
                        document.getElementById('editUserDateNaissance').value = data.date_naissance;
                        document.getElementById('editUserSexe').value = data.sexe;
                        document.getElementById('editUserAdresse').value = data.adresse;
                    }

                    toggleEditSpecialite(data.role);
                    document.getElementById('editUserModal').classList.add('show');
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Erreur lors du chargement des données utilisateur');
                });
        }

        // Edit Appointment Function
        function editAppointment(rdvId) {
            // Fetch appointment data via AJAX
            fetch(`get_appointment_data.php?id=${rdvId}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('editRdvId').value = data.id;
                    document.getElementById('editRdvDate').value = data.date_rdv;
                    document.getElementById('editRdvHeure').value = data.heure;
                    document.getElementById('editRdvMotif').value = data.motif;
                    document.getElementById('editRdvLieu').value = data.lieu;
                    document.getElementById('editRdvStatut').value = data.statut;

                    document.getElementById('editAppointmentModal').classList.add('show');
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Erreur lors du chargement des données du rendez-vous');
                });
        }

        // View Functions
        function viewAppointment(rdvId) {
            // Redirect to appointment details or show modal
            window.location.href = `?view=appointment_details&id=${rdvId}`;
        }

        function viewPrescription(ordoId) {
            // Redirect to prescription details or show modal
            window.location.href = `?view=prescription_details&id=${ordoId}`;
        }

        function editHospitalization(hospId) {
            // Similar to edit appointment
            alert('Fonctionnalité de modification d\'hospitalisation à implémenter');
        }

        // Cancel Functions
        function cancelAppointment(rdvId) {
            if (confirm('Êtes-vous sûr de vouloir annuler ce rendez-vous ?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `<input type="hidden" name="cancel_appointment" value="1">
                                 <input type="hidden" name="rdv_id" value="${rdvId}">`;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function dischargePatient(hospId) {
            if (confirm('Êtes-vous sûr de vouloir effectuer la sortie de ce patient ?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `<input type="hidden" name="discharge_patient" value="1">
                                 <input type="hidden" name="hospitalisation_id" value="${hospId}">`;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Confirm Delete
        function confirmDelete(userId, userName, userRole) {
            const roles = {
                'patient': 'patient',
                'medecin': 'médecin',
                'assistant': 'assistant'
            };
            const frenchRole = roles[userRole.toLowerCase()] || 'utilisateur';

            if (confirm(`Êtes-vous sûr de vouloir supprimer le ${frenchRole} ${userName} ?`)) {
                window.location.href = `?delete_id=${userId}`;
            }
        }

        // Export Excel
        document.getElementById('exportExcelBtn').addEventListener('click', function() {
            alert('Export Excel sera implémenté');
        });

        // Save Settings
        document.getElementById('saveSettingsBtn').addEventListener('click', function() {
            alert('Les paramètres ont été sauvegardés');
        });

        // Close modals when clicking outside
        window.addEventListener('click', function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.classList.remove('show');
                }
            });
        });

        // Show success/error message


        // Set minimum date for appointments to today
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            const dateInputs = document.querySelectorAll('input[name="date_rdv"]');
            dateInputs.forEach(input => {
                input.min = today;
            });
        });
    </script>

    <script>
        // --- SCRIPT POUR GÉRER LES MODALES ---

        // Fonction pour ouvrir une modale
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('show');
            }
        }

        // Fonction pour fermer une modale
        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('show');
            }
        }

        // Ajout des écouteurs d'événements pour les boutons d'ouverture
        document.getElementById('addPatientBtn')?.addEventListener('click', () => openModal('addPatientModal'));
        document.getElementById('addUserBtn')?.addEventListener('click', () => openModal('addUserModal'));
        document.getElementById('addAppointmentBtn')?.addEventListener('click', () => openModal('addAppointmentModal'));
        document.getElementById('addHospitalizationBtn')?.addEventListener('click', () => openModal('addHospitalizationModal'));
        // Ajoutez ici d'autres boutons si nécessaire

        // Ajout des écouteurs pour les boutons de fermeture (croix et "Annuler")
        document.querySelectorAll('[data-modal-close]').forEach(button => {
            button.addEventListener('click', () => {
                const modalId = button.getAttribute('data-modal-close');
                closeModal(modalId);
            });
        });

        // Fermer la modale en cliquant à l'extérieur
        window.addEventListener('click', (event) => {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
            }
        });

        // Fonction pour afficher/cacher le champ spécialité dans la modale
        function toggleSpecialite(role, groupId) {
            const specialiteGroup = document.getElementById(groupId);
            if (specialiteGroup) {
                if (role === 'medecin') {
                    specialiteGroup.style.display = 'block';
                    specialiteGroup.querySelector('input').required = true;
                } else {
                    specialiteGroup.style.display = 'none';
                    specialiteGroup.querySelector('input').required = false;
                }
            }
        }

        // ... (Le reste de votre JavaScript pour les dropdowns, onglets, etc. reste ici) ...
    </script>
    <script>
        // Fonctionnalité de basculement de la sidebar
        document.addEventListener('DOMContentLoaded', function() {
            const toggleSidebarBtn = document.getElementById('toggleSidebarBtn');
            const sidebar = document.querySelector('.sidebar');
            const adminContainer = document.querySelector('.admin-container');

            // Vérifier l'état sauvegardé de la sidebar
            const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            if (isCollapsed) {
                sidebar.classList.add('collapsed');
                adminContainer.classList.add('collapsed');
                toggleSidebarBtn.querySelector('i').classList.replace('fa-angle-left', 'fa-angle-right');
            }

            // Événement de clic sur le bouton
            toggleSidebarBtn.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                adminContainer.classList.toggle('collapsed');

                const isCurrentlyCollapsed = sidebar.classList.contains('collapsed');
                localStorage.setItem('sidebarCollapsed', isCurrentlyCollapsed);

                // Changer l'icône
                if (isCurrentlyCollapsed) {
                    toggleSidebarBtn.querySelector('i').classList.replace('fa-angle-left', 'fa-angle-right');
                } else {
                    toggleSidebarBtn.querySelector('i').classList.replace('fa-angle-right', 'fa-angle-left');
                }
            });
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // --- GESTION GÉNÉRIQUE DES MODALES ---
            const openModalTriggers = document.querySelectorAll('[data-modal-open]');
            const closeModalTriggers = document.querySelectorAll('[data-modal-close]');

            // Ouvrir une modale
            openModalTriggers.forEach(trigger => {
                trigger.addEventListener('click', (event) => {
                    event.preventDefault(); // Empêche le comportement par défaut (ex: soumission de formulaire)
                    const modalId = trigger.getAttribute('data-modal-open');
                    const modal = document.getElementById(modalId);
                    if (modal) {
                        // Si c'est une modale d'édition, on charge les données depuis le bouton lui-même
                        if (modalId === 'editUserModal') {
                            loadUserDataFromButton(trigger);
                        }
                        if (modalId === 'editAppointmentModal') {
                            loadAppointmentDataFromButton(trigger);
                        }
                        modal.classList.add('show');
                    }
                });
            });

            // Fermer une modale
            closeModalTriggers.forEach(trigger => {
                trigger.addEventListener('click', () => {
                    const modalId = trigger.getAttribute('data-modal-close');
                    document.getElementById(modalId).classList.remove('show');
                });
            });

            // Fermer en cliquant à l'extérieur
            window.addEventListener('click', (event) => {
                if (event.target.classList.contains('modal')) {
                    event.target.classList.remove('show');
                }
            });

            // --- FONCTIONS DE CHARGEMENT POUR L'ÉDITION (SANS FETCH) ---

            function loadUserDataFromButton(button) {
                const dataset = button.dataset;

                document.getElementById('editUserId').value = dataset.userId;
                document.getElementById('editUserNom').value = dataset.nom;
                document.getElementById('editUserPrenom').value = dataset.prenom;
                document.getElementById('editUserEmail').value = dataset.email;
                document.getElementById('editUserTelephone').value = dataset.telephone;
                document.getElementById('editUserRole').value = dataset.role;

                toggleEditSpecialite(dataset.role);

                if (dataset.role === 'medecin') {
                    document.getElementById('editUserSpecialite').value = dataset.specialite;
                }

                if (dataset.role === 'patient') {
                    document.getElementById('editUserDateNaissance').value = dataset.dateNaissance;
                    document.getElementById('editUserSexe').value = dataset.sexe;
                    document.getElementById('editUserAdresse').value = dataset.adresse;
                }
            }

            function loadAppointmentDataFromButton(button) {
                const dataset = button.dataset;

                document.getElementById('editRdvId').value = dataset.rdvId;
                document.getElementById('editRdvDate').value = dataset.dateRdv;
                document.getElementById('editRdvHeure').value = dataset.heure;
                document.getElementById('editRdvMotif').value = dataset.motif;
                document.getElementById('editRdvLieu').value = dataset.lieu;
                document.getElementById('editRdvStatut').value = dataset.statut;
            }

            // --- FONCTIONS UTILITAIRES ---

            window.toggleEditSpecialite = function(role) {
                const specialiteGroup = document.getElementById('editSpecialiteGroup');
                const patientFields = document.getElementById('editPatientFields');
                specialiteGroup.style.display = (role === 'medecin') ? 'block' : 'none';
                patientFields.style.display = (role === 'patient') ? 'block' : 'none';
            };

            // Assurez-vous que vos autres fonctions (confirmDelete, etc.) sont aussi ici
            window.confirmDelete = function(userId, userName, userRole) {
                const roles = {
                    'patient': 'patient',
                    'medecin': 'médecin',
                    'assistant': 'assistant'
                };
                const frenchRole = roles[userRole.toLowerCase()] || 'utilisateur';
                if (confirm(`Êtes-vous sûr de vouloir supprimer le ${frenchRole} ${userName} ?`)) {
                    window.location.href = `?delete_id=${userId}`;
                }
            }

            // Dans la section <script> à la fin de votre fichier

            window.cancelAppointment = function(rdvId) {
                Swal.fire({
                    title: 'Annuler le rendez-vous ?',
                    text: "Cette action marquera le rendez-vous comme annulé.",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Oui, annuler',
                    cancelButtonText: 'Non, conserver'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Création d'un formulaire invisible pour envoyer la requête POST
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.style.display = 'none'; // Le formulaire n'est pas visible par l'utilisateur
                        form.action = 'dashboard_administrateur.php?view=appointments'; // On redirige vers la vue des RDV

                        // Ajout des données nécessaires
                        const rdvIdInput = document.createElement('input');
                        rdvIdInput.type = 'hidden';
                        rdvIdInput.name = 'rdv_id';
                        rdvIdInput.value = rdvId;
                        form.appendChild(rdvIdInput);

                        const actionInput = document.createElement('input');
                        actionInput.type = 'hidden';
                        actionInput.name = 'cancel_appointment'; // Le nom de notre action
                        actionInput.value = '1';
                        form.appendChild(actionInput);

                        // Ajout du formulaire à la page et soumission
                        document.body.appendChild(form);
                        form.submit();
                    }
                });
            }


            // ... (gardez ici vos autres scripts comme la gestion du dropdown du profil, etc.)
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // --- GESTION DES MESSAGES DE SESSION AVEC SWEETALERT2 ---
            <?php
            if (isset($_SESSION['message']) && isset($_SESSION['message_type'])) {
                $message = addslashes($_SESSION['message']);
                $type = $_SESSION['message_type']; // 'success', 'error', 'warning', 'info'
                $title = '';
                switch ($type) {
                    case 'success':
                        $title = 'Succès !';
                        break;
                    case 'error':
                        $title = 'Erreur !';
                        break;
                    case 'warning':
                        $title = 'Attention !';
                        break;
                    default:
                        $title = 'Information';
                        break;
                }

                echo "Swal.fire({
            title: '{$title}',
            text: '{$message}',
            icon: '{$type}',
            confirmButtonText: 'OK',
            timer: 5000,
            timerProgressBar: true
        });";

                unset($_SESSION['message']);
                unset($_SESSION['message_type']);
            }
            ?>

            // --- GESTION DES MODALES ---
            function openModal(modalId) {
                const modal = document.getElementById(modalId);
                if (modal) modal.classList.add('show');
            }

            function closeModal(modalId) {
                const modal = document.getElementById(modalId);
                if (modal) modal.classList.remove('show');
            }

            document.querySelectorAll('[data-modal-open]').forEach(trigger => {
                trigger.addEventListener('click', (event) => {
                    event.preventDefault();
                    const modalId = trigger.getAttribute('data-modal-open');
                    openModal(modalId);
                });
            });

            document.querySelectorAll('[data-modal-close]').forEach(trigger => {
                trigger.addEventListener('click', () => {
                    const modalId = trigger.getAttribute('data-modal-close');
                    closeModal(modalId);
                });
            });

            // --- GESTION DES BOUTONS D'ACTION ---

            // Confirmation de suppression
            window.confirmDelete = function(userId, userName, userRole) {
                const roles = {
                    'patient': 'le patient',
                    'medecin': 'le médecin',
                    'assistant': 'l\'assistant'
                };
                const frenchRole = roles[userRole.toLowerCase()] || 'l\'utilisateur';
                const redirectView = (userRole.toLowerCase() === 'patient') ? 'patients' : 'users';

                Swal.fire({
                    title: 'Êtes-vous sûr ?',
                    html: `Vous êtes sur le point de supprimer ${frenchRole} <strong>${userName}</strong>.<br>Cette action est irréversible !`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Oui, supprimer !',
                    cancelButtonText: 'Annuler'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Redirection correcte après suppression
                        window.location.href = `dashboard_administrateur.php?view=${redirectView}&delete_id=${userId}`;
                    }
                });
            }

            // Annulation d'un rendez-vous
            window.cancelAppointment = function(rdvId) {
                Swal.fire({
                    title: 'Annuler le rendez-vous ?',
                    text: "Cette action marquera le rendez-vous comme annulé.",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Oui, annuler',
                    cancelButtonText: 'Non, conserver'
                }).then((result) => {
                    if (result.isConfirmed) {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.style.display = 'none';
                        form.action = 'dashboard_administrateur.php?view=appointments';
                        form.innerHTML = `<input type="hidden" name="cancel_appointment" value="1"><input type="hidden" name="rdv_id" value="${rdvId}">`;
                        document.body.appendChild(form);
                        form.submit();
                    }
                });
            }

            // Sortie d'hospitalisation
            window.dischargePatient = function(hospId) {
                Swal.fire({
                    title: 'Confirmer la sortie du patient ?',
                    text: "La date de sortie sera enregistrée à aujourd'hui.",
                    icon: 'info',
                    showCancelButton: true,
                    confirmButtonColor: '#28a745',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Oui, confirmer la sortie',
                    cancelButtonText: 'Annuler'
                }).then((result) => {
                    if (result.isConfirmed) {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.style.display = 'none';
                        form.action = 'dashboard_administrateur.php?view=hospitalized';
                        form.innerHTML = `<input type="hidden" name="discharge_patient" value="1"><input type="hidden" name="hospitalisation_id" value="${hospId}">`;
                        document.body.appendChild(form);
                        form.submit();
                    }
                });
            }

            // --- AUTRES SCRIPTS (Dropdown, Sidebar, etc.) ---
            // (Conservez ici vos autres scripts existants pour le dropdown, la sidebar, etc.)
        });
    </script>


    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // --- GESTION DES MESSAGES DE SESSION AVEC SWEETALERT2 ---
            <?php
            if (isset($_SESSION['message']) && isset($_SESSION['message_type'])) {
                $message = addslashes($_SESSION['message']);
                $type = $_SESSION['message_type']; // 'success', 'error', 'warning', 'info'
                $title = '';
                switch ($type) {
                    case 'success':
                        $title = 'Succès !';
                        break;
                    case 'error':
                        $title = 'Erreur !';
                        break;
                    case 'warning':
                        $title = 'Attention !';
                        break;
                    default:
                        $title = 'Information';
                        break;
                }

                // Ce code génère le popup SweetAlert
                echo "Swal.fire({
            title: '{$title}',
            text: '{$message}',
            icon: '{$type}',
            confirmButtonText: 'OK',
            timer: 5000, // Le popup disparaît après 5 secondes
            timerProgressBar: true
        });";

                // Et on nettoie la session pour que le message n'apparaisse qu'une seule fois
                unset($_SESSION['message']);
                unset($_SESSION['message_type']);
            }
            ?>

            // ... (le reste de votre code JavaScript)
        });
    </script>



    <script>
        // Extrait du script précédent
        const newProfilePicUrl = data.new_photo_path;
        if (newProfilePicUrl) {
            // Cette ligne est la clé !
            const allAdminPics = document.querySelectorAll('.admin-profile-pic');
            allAdminPics.forEach(pic => {
                pic.src = newProfilePicUrl + '?t=' + new Date().getTime();
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            // ... (votre code existant pour SweetAlert, les modales, etc.)

            // --- GESTION DE LA MISE À JOUR DU PROFIL ---
            const profileForm = document.getElementById('profileForm');
            if (profileForm) {
                profileForm.addEventListener('submit', function(e) {
                    e.preventDefault(); // On empêche la soumission normale pour la gérer nous-mêmes

                    const formData = new FormData(this);

                    // On utilise fetch pour envoyer les données en arrière-plan (AJAX)
                    fetch('dashboard_administrateur.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json()) // On attend une réponse JSON du serveur
                        .then(data => {
                            if (data.success) {
                                // 1. Mettre à jour toutes les images de profil avec la nouvelle URL
                                const newProfilePicUrl = data.new_photo_path;
                                if (newProfilePicUrl) {
                                    const allAdminPics = document.querySelectorAll('.admin-profile-pic');
                                    allAdminPics.forEach(pic => {
                                        pic.src = newProfilePicUrl + '?t=' + new Date().getTime(); // Le paramètre '?t=' force le navigateur à recharger l'image
                                    });
                                }

                                // 2. Mettre à jour le nom de l'admin si changé
                                const newAdminName = data.new_name;
                                if (newAdminName) {
                                    document.querySelectorAll('.profile-name, .user-name').forEach(el => {
                                        el.textContent = newAdminName;
                                    });
                                }

                                // 3. Fermer la modale
                                closeModal('profileModal');

                                // 4. Afficher le message de succès
                                Swal.fire({
                                    title: 'Succès !',
                                    text: data.message,
                                    icon: 'success',
                                    timer: 3000,
                                    timerProgressBar: true
                                });

                            } else {
                                // Afficher l'erreur
                                Swal.fire({
                                    title: 'Erreur !',
                                    text: data.message || "Une erreur inconnue est survenue.",
                                    icon: 'error'
                                });
                            }
                        })
                        .catch(error => {
                            console.error('Erreur lors de la soumission du formulaire:', error);
                            Swal.fire('Erreur', 'Impossible de contacter le serveur.', 'error');
                        });
                });
            }

            // ... (le reste de votre code)
        });
    </script>
    <script>
        function loadUserDataFromButton(button) {
            const dataset = button.dataset;
            const role = dataset.role;

            // Remplissage des champs communs
            document.getElementById('editUserId').value = dataset.userId;
            document.getElementById('editUserNom').value = dataset.nom;
            document.getElementById('editUserPrenom').value = dataset.prenom;
            document.getElementById('editUserEmail').value = dataset.email;
            document.getElementById('editUserTelephone').value = dataset.telephone;
            document.getElementById('editUserRole').value = role;

            // Appel de la fonction pour afficher/cacher les champs spécifiques
            toggleEditFields(role);

            // Remplissage des champs spécifiques
            if (role === 'medecin') {
                document.getElementById('editUserSpecialite').value = dataset.specialite;
            } else if (role === 'patient') {
                // On remplit les champs du patient
                document.getElementById('editUserDateNaissance').value = dataset.dateNaissance;
                document.getElementById('editUserSexe').value = dataset.sexe;
                document.getElementById('editUserAdresse').value = dataset.adresse;

                // Et on met à jour le champ statut
                const statutInput = document.getElementById('editUserStatut');
                statutInput.value = dataset.statutHospitalisation;

                // On change la couleur du champ pour mieux visualiser le statut
                if (dataset.statutHospitalisation === 'Hospitalisé') {
                    statutInput.style.backgroundColor = '#f8d7da'; // Rouge clair
                    statutInput.style.color = '#721c24'; // Rouge foncé
                } else {
                    statutInput.style.backgroundColor = '#d4edda'; // Vert clair
                    statutInput.style.color = '#155724'; // Vert foncé
                }
            }
        }

        // Fonction unique pour gérer la visibilité de tous les champs conditionnels
        window.toggleEditFields = function(role) {
            // On récupère tous les groupes de champs
            const roleGroup = document.getElementById('editRoleGroup');
            const statutGroup = document.getElementById('editStatutGroup');
            const specialiteGroup = document.getElementById('editSpecialiteGroup');
            const patientFields = document.getElementById('editPatientFields');

            if (role === 'patient') {
                roleGroup.style.display = 'none'; // On cache le sélecteur de rôle
                statutGroup.style.display = 'block'; // On affiche le statut
                patientFields.style.display = 'block'; // On affiche les champs patient
                specialiteGroup.style.display = 'none'; // On cache les champs médecin
            } else {
                roleGroup.style.display = 'block'; // On affiche le sélecteur de rôle
                statutGroup.style.display = 'none'; // On cache le statut
                patientFields.style.display = 'none'; // On cache les champs patient

                // On affiche la spécialité seulement si c'est un médecin
                specialiteGroup.style.display = (role === 'medecin') ? 'block' : 'none';
            }
        };

        // Assurez-vous que le reste de votre script est bien là
        document.addEventListener('DOMContentLoaded', function() {
            const openModalTriggers = document.querySelectorAll('[data-modal-open]');

            openModalTriggers.forEach(trigger => {
                trigger.addEventListener('click', (event) => {
                    event.preventDefault();
                    const modalId = trigger.getAttribute('data-modal-open');
                    const modal = document.getElementById(modalId);
                    if (modal) {
                        if (modalId === 'editUserModal') {
                            // C'est ici que la fonction est appelée
                            loadUserDataFromButton(trigger);
                        }
                        // ... gestion des autres modales
                        modal.classList.add('show');
                    }
                });
            });

            // ... reste de votre code DOMContentLoaded
        });
    </script>


    </script>


</body>

</html>
<?php
$conn->close();
?>