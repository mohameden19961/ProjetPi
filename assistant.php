<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'assistant') {
    header("Location: connection.php");
    exit;
}

$conn = new mysqli("localhost", "root", "", "gestion_cabinet_medical");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);
$conn->set_charset("utf8");

// $id_assistant = intval($_SESSION['id_utilisateur']);
$stmt = $conn->prepare("SELECT nom, prenom, email FROM utilisateur WHERE id_utilisateur = ?");
$stmt->bind_param("i", $id_assistant);
$stmt->execute();
$assistant = $stmt->get_result()->fetch_assoc();
$stmt->close();

$action = $_GET['action'] ?? '';
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Add patient functionality
    if ($action === 'add_patient') {
        $nom = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $date_naissance = $_POST['date_naissance'] ?? '';
        $sexe = $_POST['sexe'] ?? '';
        $adresse = trim($_POST['adresse'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $dossier_medical = trim($_POST['dossier_medical'] ?? '');

        if (!$nom || !$prenom || !$date_naissance || !$sexe || !$email) {
            $error = 'Veuillez remplir tous les champs obligatoires';
        } else {
            $stmt = $conn->prepare("SELECT id_patient FROM patient WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();

            if ($stmt->get_result()->num_rows > 0) {
                $error = 'Un patient avec cet email existe déjà';
            } else {
                $stmt->close();
                $stmt = $conn->prepare("
                    INSERT INTO patient (nom, prenom, date_naissance, sexe, adresse, telephone, email, dossier_medical)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("ssssssss", $nom, $prenom, $date_naissance, $sexe, $adresse, $telephone, $email, $dossier_medical);

                if ($stmt->execute()) {
                    $stmt->close();
                    header("Location: assistant.php?success=" . urlencode("Patient ajouté avec succès"));
                    exit;
                } else {
                    $error = "Erreur lors de l\\'ajout du patient : " . $conn->error;
                }
            }
            $stmt->close();
        }
    }

    // Add appointment functionality - FIXED
    if ($action === 'add_rdv') {
        $patient_id = intval($_POST['patient_id'] ?? 0);
        $medecin_id = intval($_POST['medecin_id'] ?? 0);
        $date_rdv = $_POST['date_rdv'] ?? '';
        $heure = $_POST['heure'] ?? '';
        $lieu = trim($_POST['lieu'] ?? 'Clinique A');
        $motif = trim($_POST['motif'] ?? '');

        if (!$patient_id || !$medecin_id || !$date_rdv || !$heure || !$motif) {
            $error = 'Veuillez remplir tous les champs obligatoires';
        } else {
            // Check if a treatment exists for this patient-medecin combination
            $stmt = $conn->prepare("
                SELECT id_traitement FROM traitement 
                WHERE id_patient = ? AND id_medecin = ?
                ORDER BY id_traitement DESC LIMIT 1
            ");
            $stmt->bind_param("ii", $patient_id, $medecin_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                $stmt->close();
                // Create a new treatment if none exists
                $stmt = $conn->prepare("
                    INSERT INTO traitement (id_patient, id_medecin, date_debut, date_fin)
                    VALUES (?, ?, CURDATE(), NULL)
                ");
                $stmt->bind_param("ii", $patient_id, $medecin_id);

                if ($stmt->execute()) {
                    $traitement_id = $stmt->insert_id;
                    $stmt->close();
                } else {
                    $error = 'Erreur lors de la création du traitement : ' . $conn->error;
                    $stmt->close();
                    // Use header redirect for error as well
                    header("Location: assistant.php?error=" . urlencode($error));
                    exit;
                }
            } else {
                $row = $result->fetch_assoc();
                $traitement_id = $row['id_traitement'];
                $stmt->close();
            }

            // Insert the appointment
            $stmt = $conn->prepare("
                INSERT INTO rendezvous (id_traitement, date_rdv, heure, lieu, motif, statut)
                VALUES (?, ?, ?, ?, ?, 'en_attente')
            ");
            $stmt->bind_param("issss", $traitement_id, $date_rdv, $heure, $lieu, $motif);

            if ($stmt->execute()) {
                $stmt->close();
                header("Location: assistant.php?success=" . urlencode("Rendez-vous ajouté avec succès"));
                exit;
            } else {
                $error = "Erreur lors de l\\'ajout du rendez-vous : " . $conn->error;
                $stmt->close();
                // Use header redirect for error as well
                header("Location: assistant.php?error=" . urlencode($error));
                exit;
            }
        }
        // Removed goto end_rdv_processing; as it's not needed with exit;
    }

    // Add exam functionality
    if ($action === 'add_exam_note') {
        $patient_id = intval($_POST['patient_id'] ?? 0);
        $type_examen = trim($_POST['type_examen'] ?? '');
        $file = $_FILES['exam_file'] ?? null;

        if (!$patient_id || !$type_examen || !$file || $file['error'] !== UPLOAD_ERR_OK) {
            $error = 'Veuillez remplir tous les champs obligatoires et sélectionner un fichier valide';
        } else {
            // Get the most recent treatment for this patient
            $stmt = $conn->prepare("SELECT id_traitement FROM traitement WHERE id_patient = ? ORDER BY id_traitement DESC LIMIT 1");
            $stmt->bind_param("i", $patient_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $traitement_id = $row['id_traitement'];
                $stmt->close();

                $file_name = basename($file['name']);
                $target_dir = "uploadsExamen/";

                if (!file_exists($target_dir)) {
                    if (!mkdir($target_dir, 0775, true)) {
                        $error = 'Impossible de créer le répertoire uploadsExamen/';
                        // Use header redirect for error as well
                        header("Location: assistant.php?error=" . urlencode($error));
                        exit;
                    }
                }

                if (!is_writable($target_dir)) {
                    $error = "Le répertoire uploadsExamen/ n\\'est pas accessible en écriture.";
                } else {
                    $target_file = $target_dir . uniqid() . '_' . $file_name;
                    $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

                    $allowed_types = ['pdf', 'jpg', 'jpeg', 'png'];
                    if (!in_array($file_type, $allowed_types)) {
                        $error = 'Seuls les fichiers PDF, JPG, JPEG et PNG sont autorisés';
                    } elseif (move_uploaded_file($file['tmp_name'], $target_file)) {
                        $stmt = $conn->prepare("INSERT INTO examen (id_traitement, type_examen, résultat, date_examen) VALUES (?, ?, ?, CURDATE())");
                        $stmt->bind_param("iss", $traitement_id, $type_examen, $target_file);

                        if ($stmt->execute()) {
                            $stmt->close();
                            header("Location: assistant.php?success=" . urlencode("Note d'examen ajoutée avec succès"));
                            exit;
                        } else {
                            $error = "Erreur lors de l\\'ajout de la note d\\'examen : " . $conn->error;
                            if (file_exists($target_file)) {
                                unlink($target_file);
                            }
                            $stmt->close();
                            // Use header redirect for error as well
                            header("Location: assistant.php?error=" . urlencode($error));
                            exit;
                        }
                    } else {
                        $error = 'Erreur lors du téléchargement du fichier';
                    }
                }
            } else {
                $error = "Aucun traitement trouvé pour ce patient. Veuillez d\\'abord créer un traitement.";
                $stmt->close();
            }
        }
        // Removed goto end_exam_processing; as it's not needed with exit;
        // If an error occurred and no redirect happened, ensure it happens now
        if ($error) {
            header("Location: assistant.php?error=" . urlencode($error));
            exit;
        }
    }

    // Add hospitalisation functionality - FIXED
    if ($action === 'add_hospitalisation') {
        $id_patient = intval($_POST['id_patient'] ?? 0);
        $date_entree = $_POST['date_entree'] ?? '';
        $date_sortie = $_POST['date_sortie'] ?? null;
        $service = trim($_POST['service'] ?? '');

        if (!$id_patient || !$date_entree || !$service) {
            $error = 'Veuillez remplir tous les champs obligatoires';
        } else {
            $stmt = $conn->prepare("
                INSERT INTO hospitalisation (id_patient, date_entree, date_sortie, service)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param("isss", $id_patient, $date_entree, $date_sortie, $service);

            if ($stmt->execute()) {
                $stmt->close();
                header("Location: assistant.php?success=" . urlencode("Hospitalisation ajoutée avec succès"));
                exit;
            } else {
                $error = "Erreur lors de l\\'ajout de l\\'hospitalisation : " . $conn->error;
                $stmt->close();
                header("Location: assistant.php?error=" . urlencode($error));
                exit;
            }
        }
    }

    // Update patient functionality
    if ($action === 'update_patient') {
        $id_patient = intval($_POST['id_patient'] ?? 0);
        $nom = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $date_naissance = $_POST['date_naissance'] ?? '';
        $sexe = $_POST['sexe'] ?? '';
        $adresse = trim($_POST['adresse'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if (!$id_patient || !$nom || !$prenom || !$date_naissance || !$sexe || !$email) {
            $error = 'Veuillez remplir tous les champs obligatoires';
        } else {
            $stmt = $conn->prepare("
                UPDATE patient 
                SET nom = ?, prenom = ?, date_naissance = ?, sexe = ?, adresse = ?, telephone = ?, email = ?
                WHERE id_patient = ?
            ");
            $stmt->bind_param("sssssssi", $nom, $prenom, $date_naissance, $sexe, $adresse, $telephone, $email, $id_patient);

            if ($stmt->execute()) {
                $stmt->close();
                header("Location: assistant.php?success=" . urlencode("Patient modifié avec succès"));
                exit;
            } else {
                $error = 'Erreur lors de la modification du patient : ' . $conn->error;
                $stmt->close();
                header("Location: assistant.php?error=" . urlencode($error));
                exit;
            }
        }
    }
}

// Handle GET requests for deletion
if ($action === 'cancel_rdv' && isset($_GET['rdv_id'])) {
    $rdv_id = intval($_GET['rdv_id']);

    $stmt = $conn->prepare("DELETE FROM rendezvous WHERE id_rdv = ?");
    $stmt->bind_param("i", $rdv_id);

    if ($stmt->execute()) {
        $stmt->close();
        header("Location: assistant.php?success=" . urlencode("Rendez-vous annulé avec succès"));
        exit;
    } else {
        $stmt->close();
        header("Location: assistant.php?error=" . urlencode("Erreur lors de l'annulation du rendez-vous"));
        exit;
    }
}

// Handle hospitalisation deletion - FIXED
if ($action === 'delete_hospitalisation' && isset($_GET['id'])) {
    $id_hospitalisation = intval($_GET['id']);

    $stmt = $conn->prepare("DELETE FROM hospitalisation WHERE id_hospitalisation = ?");
    $stmt->bind_param("i", $id_hospitalisation);

    if ($stmt->execute()) {
        $stmt->close();
        // Instead of echoing JSON, redirect with success message
        header("Location: assistant.php?success=" . urlencode("Hospitalisation supprimée avec succès"));
    } else {
        $stmt->close();
        // Redirect with error message
        header("Location: assistant.php?error=" . urlencode("Erreur lors de la suppression: " . $conn->error));
    }
    exit;
}

// FIXED: Get real statistics from database
$stats = [];

// Count total patients
$result = $conn->query("SELECT COUNT(*) as count FROM patient");
$stats['patients'] = $result->fetch_assoc()['count'];

// Count today's appointments
$result = $conn->query("SELECT COUNT(*) as count FROM rendezvous WHERE date_rdv = CURDATE()");
$stats['rdv_today'] = $result->fetch_assoc()['count'];

// Count total hospitalizations
$result = $conn->query("SELECT COUNT(*) as count FROM hospitalisation");
$stats['hospitalisations'] = $result->fetch_assoc()['count'];

// Count exams this month
$result = $conn->query("SELECT COUNT(*) as count FROM examen WHERE MONTH(date_examen) = MONTH(CURDATE()) AND YEAR(date_examen) = YEAR(CURDATE())");
$stats['exams_month'] = $result->fetch_assoc()['count'];

// Get patients
$patients = $conn->query("SELECT * FROM patient ORDER BY nom, prenom");

// Get patients with treatments
$patients_with_treatment = $conn->query("
    SELECT DISTINCT p.* 
    FROM patient p
    JOIN traitement t ON p.id_patient = t.id_patient
    ORDER BY p.nom, p.prenom
");

// Get all patients for hospitalisation
$all_patients = $conn->query("SELECT * FROM patient ORDER BY nom, prenom");

// Get medecins
$medecins = $conn->query("SELECT u.nom, u.prenom, m.id_medecin FROM medecin m JOIN utilisateur u ON m.id_medecin = u.id_utilisateur ORDER BY u.nom, u.prenom");

// Get appointments for display
$rdv_stmt = $conn->prepare("
    SELECT r.id_rdv, r.date_rdv, r.heure, r.motif, r.lieu, r.statut,
           p.id_patient, p.prenom AS p_prenom, p.nom AS p_nom,
           m.id_medecin, u.prenom AS m_prenom, u.nom AS m_nom
    FROM rendezvous r
    JOIN traitement t ON r.id_traitement = t.id_traitement
    JOIN patient p ON t.id_patient = p.id_patient
    JOIN medecin m ON t.id_medecin = m.id_medecin
    JOIN utilisateur u ON m.id_medecin = u.id_utilisateur
    WHERE r.date_rdv >= CURDATE()
    ORDER BY r.date_rdv, r.heure
    LIMIT 20
");
$rdv_stmt->execute();
$rendezvous = $rdv_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$rdv_stmt->close();

// FIXED: Get hospitalizations for display
$hosp_stmt = $conn->prepare("
    SELECT h.id_hospitalisation, h.date_entree, h.date_sortie, h.service,
           p.prenom, p.nom, p.id_patient
    FROM hospitalisation h
    JOIN patient p ON h.id_patient = p.id_patient
    ORDER BY h.date_entree DESC
    LIMIT 50
");


$hosp_stmt->execute();
$hospitalisations = $hosp_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$hosp_stmt->close();

?>
<?php
// ... (après le code existant qui récupère les hospitalisations)

// NOUVEAU : Récupérer les données pour la page du dossier patient
$dossier_patient = null;
if (isset($_GET['action']) && $_GET['action'] === 'dossier' && isset($_GET['id_patient'])) {
    $id_patient_dossier = intval($_GET['id_patient']);

    // Récupérer les informations du patient
    $stmt_patient = $conn->prepare("SELECT * FROM patient WHERE id_patient = ?");
    $stmt_patient->bind_param("i", $id_patient_dossier);
    $stmt_patient->execute();
    $patient_info = $stmt_patient->get_result()->fetch_assoc();
    $stmt_patient->close();

    if ($patient_info) {
        $dossier_patient = ['info' => $patient_info];

        // Récupérer les traitements
        $stmt_traitement = $conn->prepare("
            SELECT t.*, m.nom AS medecin_nom, m.prenom AS medecin_prenom, m.spécialité
            FROM traitement t
            JOIN medecin m ON t.id_medecin = m.id_medecin
            WHERE t.id_patient = ? ORDER BY t.date_debut DESC
        ");
        $stmt_traitement->bind_param("i", $id_patient_dossier);
        $stmt_traitement->execute();
        $dossier_patient['traitements'] = $stmt_traitement->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_traitement->close();

        // Récupérer les rendez-vous
        $stmt_rdv = $conn->prepare("
            SELECT r.*, m.nom AS medecin_nom, m.prenom AS medecin_prenom
            FROM rendezvous r
            JOIN traitement t ON r.id_traitement = t.id_traitement
            JOIN medecin m ON t.id_medecin = m.id_medecin
            WHERE t.id_patient = ? ORDER BY r.date_rdv DESC, r.heure DESC
        ");
        $stmt_rdv->bind_param("i", $id_patient_dossier);
        $stmt_rdv->execute();
        $dossier_patient['rendezvous'] = $stmt_rdv->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_rdv->close();

        // Récupérer les examens
        $stmt_examen = $conn->prepare("
            SELECT e.* FROM examen e
            JOIN traitement t ON e.id_traitement = t.id_traitement
            WHERE t.id_patient = ? ORDER BY e.date_examen DESC
        ");
        $stmt_examen->bind_param("i", $id_patient_dossier);
        $stmt_examen->execute();
        $dossier_patient['examens'] = $stmt_examen->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_examen->close();

        // NOUVEAU : Récupérer les ordonnances
        $stmt_ordonnance = $conn->prepare("
            SELECT o.*, t.id_traitement, m.nom AS medecin_nom, m.prenom AS medecin_prenom
            FROM ordonnance o
            JOIN traitement t ON o.id_traitement = t.id_traitement
            JOIN medecin m ON t.id_medecin = m.id_medecin
            WHERE t.id_patient = ? ORDER BY o.date_ordonnance DESC
        ");
        $stmt_ordonnance->bind_param("i", $id_patient_dossier);
        $stmt_ordonnance->execute();
        $dossier_patient['ordonnances'] = $stmt_ordonnance->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_ordonnance->close();

        // Récupérer les hospitalisations
        $stmt_hosp = $conn->prepare("
            SELECT * FROM hospitalisation WHERE id_patient = ? ORDER BY date_entree DESC
        ");
        $stmt_hosp->bind_param("i", $id_patient_dossier);
        $stmt_hosp->execute();
        $dossier_patient['hospitalisations'] = $stmt_hosp->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_hosp->close();
    }
}
?>
<!-- Le reste de votre code HTML commence ici -->



<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Espace Assistant - Cabinet Médical</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #3498db;
            --secondary: #2c3e50;
            --success: #2ecc71;
            --info: #17a2b8;
            --light: #f8f9fa;
            --dark: #343a40;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f4f7f6;
            color: #333;
            overflow-x: hidden;
        }

        .wrapper {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 250px;
            background: linear-gradient(135deg, var(--secondary), #1a2530);
            color: #ecf0f1;
            padding: 20px;
            box-shadow: var(--shadow);
            display: flex;
            flex-direction: column;
            transition: var(--transition);
            z-index: 100;
        }

        .sidebar .logo {
            text-align: center;
            margin-bottom: 30px;
            padding: 15px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar .logo h2 {
            color: #fff;
            font-weight: 700;
            font-size: 1.8rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .sidebar .logo i {
            color: var(--primary);
            margin-right: 10px;
        }

        .sidebar ul {
            list-style: none;
            padding: 0;
            flex-grow: 1;
        }

        .sidebar ul li {
            margin-bottom: 10px;
        }

        .sidebar ul li a {
            color: #ecf0f1;
            text-decoration: none;
            padding: 12px 15px;
            display: flex;
            align-items: center;
            border-radius: 8px;
            transition: var(--transition);
            font-weight: 500;
            position: relative;
            overflow: hidden;
        }

        .sidebar ul li a:before {
            content: '';
            position: absolute;
            left: -10px;
            top: 0;
            height: 100%;
            width: 5px;
            background: var(--primary);
            transform: translateX(-20px);
            transition: var(--transition);
            border-radius: 0 5px 5px 0;
        }

        .sidebar ul li a:hover,
        .sidebar ul li a.active {
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(5px);
        }

        .sidebar ul li a:hover:before,
        .sidebar ul li a.active:before {
            transform: translateX(0);
        }

        .sidebar ul li a i {
            margin-right: 15px;
            font-size: 1.2rem;
            width: 25px;
            text-align: center;
        }

        .main-content {
            flex-grow: 1;
            padding: 25px;
            background-color: #f0f4f8;
            transition: var(--transition);
        }

        .navbar {
            background: linear-gradient(135deg, #fff, #f8fafc);
            padding: 15px 25px;
            border-radius: 15px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow);
            transition: var(--transition);
        }

        .navbar .welcome-text {
            font-size: 1.1rem;
            font-weight: 500;
            color: var(--secondary);
        }

        .navbar .welcome-text strong {
            color: var(--primary);
        }

        .quick-actions .btn {
            margin-left: 10px;
            border-radius: 50px;
            padding: 10px 20px;
            font-weight: 500;
            box-shadow: var(--shadow);
            transition: var(--transition);
        }

        .quick-actions .btn:hover {
            transform: translateY(-3px);
        }

        .card {
            background-color: #fff;
            padding: 25px;
            border-radius: 15px;
            box-shadow: var(--shadow);
            margin-bottom: 25px;
            transition: var(--transition);
            border: none;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h3 {
            margin: 0;
            font-size: 1.5rem;
            color: var(--secondary);
            font-weight: 600;
        }

        .card-header h3 i {
            color: var(--primary);
            margin-right: 10px;
        }

        .search-container {
            position: relative;
            margin-bottom: 20px;
        }

        .search-input {
            width: 100%;
            padding: 12px 45px 12px 15px;
            border: 1px solid #e2e8f0;
            border-radius: 25px;
            font-size: 14px;
            transition: var(--transition);
            background-color: #f8fafc;
        }

        .search-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.25);
            outline: none;
            background-color: #fff;
        }

        .search-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            font-size: 16px;
        }

        .table-responsive {
            margin-top: 20px;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .table {
            width: 100%;
            margin-bottom: 0;
            color: #444;
            border-collapse: separate;
            border-spacing: 0;
        }

        .table th {
            background: linear-gradient(to bottom, #f8fafc, #e2e8f0);
            color: var(--secondary);
            font-weight: 600;
            padding: 15px;
            border-top: none;
            vertical-align: middle;
        }

        .table td {
            padding: 15px;
            vertical-align: middle;
            border-top: 1px solid #edf2f7;
        }

        .table tbody tr {
            transition: var(--transition);
        }

        .table tbody tr:hover {
            background-color: #f8fafc;
            transform: translateX(3px);
        }

        .btn {
            border-radius: 50px;
            padding: 8px 18px;
            font-weight: 500;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn i {
            margin-right: 8px;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.875rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), #2980b9);
            border: none;
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success), #27ae60);
            border: none;
        }

        .btn-info {
            background: linear-gradient(135deg, var(--info), #138496);
            border: none;
        }

        .btn-danger {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            border: none;
        }

        .btn-warning {
            background: linear-gradient(135deg, #f39c12, #d35400);
            border: none;
        }

        .modal-content {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            border: none;
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary), #2980b9);
            color: #fff;
            border-bottom: none;
            padding: 20px;
        }

        .modal-title {
            font-weight: 600;
        }

        .modal-body {
            padding: 25px;
        }

        .modal-footer {
            border-top: 1px solid #eee;
            padding: 15px 25px;
        }

        .form-control {
            border-radius: 8px;
            padding: 10px 15px;
            border: 1px solid #e2e8f0;
            transition: var(--transition);
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.25);
        }

        .form-label {
            font-weight: 500;
            color: var(--secondary);
            margin-bottom: 8px;
        }

        .alert {
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 20px;
            border: none;
            box-shadow: var(--shadow);
        }

        .back-button {
            position: fixed;
            top: 20px;
            left: 280px;
            z-index: 1000;
            background: linear-gradient(135deg, var(--primary), #2980b9);
            color: white;
            border-radius: 50%;
            width: 45px;
            height: 45px;
            display: flex;
            justify-content: center;
            align-items: center;
            box-shadow: var(--shadow);
            transition: var(--transition);
            border: none;
            cursor: pointer;
        }

        .back-button:hover {
            transform: scale(1.1) rotate(-10deg);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            transition: var(--transition);
            border-left: 5px solid var(--primary);
            cursor: pointer;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: rgba(52, 152, 219, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 20px;
            font-size: 24px;
            color: var(--primary);
        }

        .stat-content h3 {
            margin: 0;
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--secondary);
        }

        .stat-content p {
            margin: 5px 0 0;
            font-size: 0.9rem;
            color: #666;
        }

        .action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .action-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: var(--shadow);
            transition: var(--transition);
            cursor: pointer;
            text-align: center;
        }

        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .action-card i {
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 15px;
        }

        .action-card h4 {
            color: var(--secondary);
            margin-bottom: 10px;
            font-weight: 600;
        }

        .action-card p {
            color: #666;
            margin-bottom: 15px;
        }

        .content-section {
            animation: fadeIn 0.5s ease-in-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .wrapper {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                position: relative;
            }

            .main-content {
                padding: 15px;
            }

            .navbar {
                flex-direction: column;
                gap: 15px;
            }

            .dashboard-stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <div class="sidebar">
            <div class="logo">
                <h2><i class="fas fa-heartbeat"></i> <span>Cabinet Médical</span></h2>
            </div>
            <ul>
                <li><a href="#" class="active" onclick="showSection('dashboard'); return false;"><i class="fas fa-tachometer-alt"></i> <span>Tableau de bord</span></a></li>
                <li><a href="#" onclick="showSection('patients'); return false;"><i class="fas fa-user-injured"></i> <span>Patients</span></a></li>
                <li><a href="#" onclick="showSection('rendezvous'); return false;"><i class="fas fa-calendar-alt"></i> <span>Rendez-vous</span></a></li>
                <li><a href="#" onclick="showSection('hospitalisation'); return false;"><i class="fas fa-hospital"></i> <span>Hospitalisation</span></a></li>
                <li><a href="#" onclick="showSection('profile'); return false;"><i class="fas fa-user-circle"></i> <span>Mon Profil</span></a></li>
                <li><a href="#" onclick="confirmLogout(); return false;"><i class="fas fa-sign-out-alt"></i> <span>Déconnexion</span></a></li>
            </ul>
        </div>

        <div class="main-content">
            <div class="navbar animate-slideIn">
                <div class="welcome-text">
                    Bienvenue, <strong><?php echo htmlspecialchars(($assistant['prenom'] ?? '') . ' ' . ($assistant['nom'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>!
                </div>
                <div class="quick-actions">
                    <button class="btn btn-primary" onclick="openModal('addPatientModal')"><i class="fas fa-user-plus"></i> Ajouter Patient</button>
                    <button class="btn btn-success" onclick="openModal('addRdvModal')"><i class="fas fa-calendar-plus"></i> Ajouter RDV</button>
                    <button class="btn btn-info" onclick="openModal('addExamModal')"><i class="fas fa-notes-medical"></i> Ajouter Examen</button>
                </div>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success animate-fadeIn" role="alert">
                    <i class="fas fa-check-circle me-2"></i> <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger animate-fadeIn" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <a href="#" class="back-button animate-slideIn" id="backButton" style="display: none;" onclick="goBack(); return false;">
                <i class="fas fa-arrow-left"></i>
            </a>

            <!-- Dashboard Section -->
            <div id="dashboardSection" class="content-section">
                <div class="card animate-fadeIn">
                    <div class="card-header">
                        <h3><i class="fas fa-tachometer-alt"></i> Tableau de bord</h3>
                    </div>
                    <div class="card-body">
                        <div class="dashboard-stats">
                            <div class="stat-card animate-slideIn" style="animation-delay: 0.1s" onclick="showSection('patients')">
                                <div class="stat-icon">
                                    <i class="fas fa-user-injured"></i>
                                </div>
                                <div class="stat-content">
                                    <h3><?= $stats['patients'] ?></h3>
                                    <p>Patients enregistrés</p>
                                </div>
                            </div>

                            <div class="stat-card animate-slideIn" style="animation-delay: 0.2s" onclick="showSection('rendezvous')">
                                <div class="stat-icon">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                                <div class="stat-content">
                                    <h3><?= $stats['rdv_today'] ?></h3>
                                    <p>RDV aujourd'hui</p>
                                </div>
                            </div>

                            <div class="stat-card animate-slideIn" style="animation-delay: 0.3s" onclick="showSection('hospitalisation')">
                                <div class="stat-icon">
                                    <i class="fas fa-hospital-user"></i>
                                </div>
                                <div class="stat-content">
                                    <h3><?= $stats['hospitalisations'] ?></h3>
                                    <p>Hospitalisations</p>
                                </div>
                            </div>

                            <div class="stat-card animate-slideIn" style="animation-delay: 0.4s" onclick="openModal('addExamModal')">
                                <div class="stat-icon">
                                    <i class="fas fa-file-medical"></i>
                                </div>
                                <div class="stat-content">
                                    <h3><?= $stats['exams_month'] ?></h3>
                                    <p>Examens ce mois</p>
                                </div>
                            </div>
                        </div>

                        <div class="action-grid">
                            <div class="action-card animate-slideIn" style="animation-delay: 0.2s" onclick="openModal('addPatientModal')">
                                <i class="fas fa-user-plus"></i>
                                <h4>Ajouter Patient</h4>
                                <p>Créer un nouveau dossier patient</p>
                                <button class="btn btn-primary">Commencer</button>
                            </div>

                            <div class="action-card animate-slideIn" style="animation-delay: 0.3s" onclick="openModal('addRdvModal')">
                                <i class="fas fa-calendar-plus"></i>
                                <h4>Planifier RDV</h4>
                                <p>Programmer un nouveau rendez-vous</p>
                                <button class="btn btn-success">Commencer</button>
                            </div>

                            <div class="action-card animate-slideIn" style="animation-delay: 0.4s" onclick="showSection('hospitalisation')">
                                <i class="fas fa-procedures"></i>
                                <h4>Hospitalisation</h4>
                                <p>Gérer les admissions patients</p>
                                <button class="btn btn-info">Commencer</button>
                            </div>

                            <div class="action-card animate-slideIn" style="animation-delay: 0.5s" onclick="openModal('addExamModal')">
                                <i class="fas fa-file-medical-alt"></i>
                                <h4>Ajouter Examen</h4>
                                <p>Enregistrer des résultats d'examens</p>
                                <button class="btn btn-warning">Commencer</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Patients Section -->
            <div id="patientsSection" class="content-section" style="display: none;">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-user-injured"></i> Liste des Patients</h3>
                        <button class="btn btn-primary" onclick="openModal('addPatientModal')"><i class="fas fa-user-plus"></i> Ajouter Patient</button>
                    </div>
                    <div class="card-body">
                        <div class="search-container">
                            <input type="text" class="search-input" id="patientsSearch" placeholder="Rechercher un patient par nom, prénom ou email...">
                            <i class="fas fa-search search-icon"></i>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped" id="patientsTable">
                                <thead>
                                    <tr>
                                        <th>Nom</th>
                                        <th>Prénom</th>
                                        <th>Email</th>
                                        <th>Téléphone</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($p = $patients->fetch_assoc()): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($p['nom'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($p['prenom'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($p['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($p['telephone'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td>
                                                <a href="assistant.php?action=dossier&id_patient=<?= $p['id_patient'] ?>" class="btn btn-info btn-sm"><i class="fas fa-folder-open"></i> Dossier</a>
                                                <button class="btn btn-warning btn-sm" onclick="openEditPatientModal(<?= $p['id_patient'] ?>, '<?= htmlspecialchars($p['nom'] ?? '', ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars($p['prenom'] ?? '', ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars($p['date_naissance'] ?? '', ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars($p['sexe'] ?? '', ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars($p['adresse'] ?? '', ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars($p['telephone'] ?? '', ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars($p['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>')"><i class="fas fa-edit"></i> Modifier</button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Rendez-vous Section -->
            <div id="rendezvousSection" class="content-section" style="display: none;">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-calendar-alt"></i> Prochains Rendez-vous</h3>
                        <button class="btn btn-success" onclick="openModal('addRdvModal')"><i class="fas fa-calendar-plus"></i> Ajouter RDV</button>
                    </div>
                    <div class="card-body">
                        <div class="search-container">
                            <input type="text" class="search-input" id="rdvSearch" placeholder="Rechercher un rendez-vous par patient, médecin ou motif...">
                            <i class="fas fa-search search-icon"></i>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped" id="rdvTable">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Heure</th>
                                        <th>Patient</th>
                                        <th>Médecin</th>
                                        <th>Motif</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rendezvous as $rdv): ?>
                                        <tr>
                                            <td><?= date('d/m/Y', strtotime($rdv['date_rdv'])) ?></td>
                                            <td><?= substr($rdv['heure'], 0, 5) ?></td>
                                            <td><?= htmlspecialchars($rdv['p_prenom'] . ' ' . $rdv['p_nom']) ?></td>
                                            <td><?= htmlspecialchars('Dr. ' . $rdv['m_prenom'] . ' ' . $rdv['m_nom']) ?></td>
                                            <td><?= htmlspecialchars($rdv['motif']) ?></td>
                                            <td>
                                                <span class="badge bg-<?= $rdv['statut'] === 'en_attente' ? 'warning' : 'success' ?>">
                                                    <?= ucfirst($rdv['statut']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-danger btn-sm" onclick="cancelAppointment(<?= $rdv['id_rdv'] ?>)"><i class="fas fa-times"></i> Annuler</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Hospitalisation Section -->
            <div id="hospitalisationSection" class="content-section" style="display: none;">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-hospital"></i> Gestion des Hospitalisations</h3>
                        <button class="btn btn-primary" onclick="openModal('addHospitalisationModal')"><i class="fas fa-plus"></i> Ajouter Hospitalisation</button>
                    </div>
                    <div class="card-body">
                        <div class="search-container">
                            <input type="text" class="search-input" id="hospitalisationSearch" placeholder="Rechercher une hospitalisation par patient ou service...">
                            <i class="fas fa-search search-icon"></i>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped" id="hospitalisationTable">
                                <thead>
                                    <tr>
                                        <th>Patient</th>
                                        <th>Date d'entrée</th>
                                        <th>Date de sortie</th>
                                        <th>Service</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($hospitalisations as $hosp): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($hosp['prenom'] . ' ' . $hosp['nom']) ?></td>
                                            <td><?= date('d/m/Y', strtotime($hosp['date_entree'])) ?></td>
                                            <td><?= $hosp['date_sortie'] ? date('d/m/Y', strtotime($hosp['date_sortie'])) : 'En cours' ?></td>
                                            <td><?= htmlspecialchars($hosp['service']) ?></td>
                                            <td>
                                                <button class="btn btn-danger btn-sm" onclick="deleteHospitalisation(<?= $hosp['id_hospitalisation'] ?>)"><i class="fas fa-trash"></i> Supprimer</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Profile Section -->
            <div id="profileSection" class="content-section" style="display: none;">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-user-circle"></i> Mon Profil</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="text-center">
                                    <img src="https://via.placeholder.com/150" class="rounded-circle img-fluid mb-3" alt="Profile">
                                </div>
                            </div>
                            <div class="col-md-9">
                                <h4><?= htmlspecialchars(($assistant['prenom'] ?? '') . ' ' . ($assistant['nom'] ?? '')) ?></h4>
                                <p><strong>Email:</strong> <?= htmlspecialchars($assistant['email'] ?? '') ?></p>
                                <p><strong>Rôle:</strong> Assistant</p>
                                <p><strong>Date d'inscription:</strong> <?= date('d/m/Y') ?></p>
                                <button class="btn btn-primary">Modifier le profil</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dossier Patient Section -->
            <div id="dossierPatientSection" class="content-section" style="display: none;">
                <?php if ($dossier_patient): ?>
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-folder-open"></i> Dossier Médical de <?= htmlspecialchars($dossier_patient['info']['prenom'] . ' ' . $dossier_patient['info']['nom']) ?></h3>
                        </div>
                        <div class="card-body">
                            <!-- Informations du Patient -->
                            <div class="mb-4">
                                <h4>Informations Personnelles</h4>
                                <p><strong>Date de Naissance:</strong> <?= !empty($dossier_patient['info']['date_naissance']) ? date('d/m/Y', strtotime($dossier_patient['info']['date_naissance'])) : 'Non renseignée' ?></p>
                                <p><strong>Sexe:</strong> <?= htmlspecialchars($dossier_patient['info']['sexe'] ?? 'Non renseigné') ?></p>
                                <p><strong>Email:</strong> <?= htmlspecialchars($dossier_patient['info']['email'] ?? '') ?></p>
                                <p><strong>Téléphone:</strong> <?= htmlspecialchars($dossier_patient['info']['telephone'] ?? '') ?></p>
                                <p><strong>Adresse:</strong> <?= htmlspecialchars($dossier_patient['info']['adresse'] ?? 'Non renseignée') ?></p>
                                <p><strong>Antécédents:</strong> <?= nl2br(htmlspecialchars($dossier_patient['info']['dossier_medical'] ?? 'Aucun')) ?></p>
                            </div>
                            <hr>
                            <!-- Traitements -->
                            <div class="mb-4">
                                <h4>Traitements</h4>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Début</th>
                                                <th>Fin</th>
                                                <th>Médecin</th>
                                                <th>Spécialité</th>
                                                <th>Diagnostic</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($dossier_patient['traitements'] as $t): ?>
                                                <tr>
                                                    <td><?= date('d/m/Y', strtotime($t['date_debut'])) ?></td>
                                                    <td><?= $t['date_fin'] ? date('d/m/Y', strtotime($t['date_fin'])) : 'En cours' ?></td>
                                                    <td>Dr. <?= htmlspecialchars($t['medecin_prenom'] . ' ' . $t['medecin_nom']) ?></td>
                                                    <td><?= htmlspecialchars($t['spécialité']) ?></td>
                                                    <td><?= htmlspecialchars($t['diagnostic'] ?? '') ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <hr>
                            <!-- Rendez-vous -->
                            <div class="mb-4">
                                <h4>Rendez-vous</h4>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Heure</th>
                                                <th>Médecin</th>
                                                <th>Motif</th>
                                                <th>Statut</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($dossier_patient['rendezvous'] as $r): ?>
                                                <tr>
                                                    <td><?= date('d/m/Y', strtotime($r['date_rdv'])) ?></td>
                                                    <td><?= substr($r['heure'], 0, 5) ?></td>
                                                    <td>Dr. <?= htmlspecialchars($r['medecin_prenom'] . ' ' . $r['medecin_nom']) ?></td>
                                                    <td><?= htmlspecialchars($r['motif']) ?></td>
                                                    <td><span class="badge bg-<?= $r['statut'] === 'confirme' ? 'success' : ($r['statut'] === 'annule' ? 'danger' : 'warning') ?>"><?= ucfirst($r['statut']) ?></span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <hr>
                            <!-- NOUVELLE SECTION : Ordonnances -->
                            <div class="mb-4">
                                <h4><i class="fas fa-prescription-bottle-alt"></i> Ordonnances</h4>
                                <?php if (!empty($dossier_patient['ordonnances'])): ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Médecin</th>
                                                    <th>Médicaments</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($dossier_patient['ordonnances'] as $ord): ?>
                                                    <tr>
                                                        <td><?= date('d/m/Y', strtotime($ord['date_ordonnance'])) ?></td>
                                                        <td>Dr. <?= htmlspecialchars($ord['medecin_prenom'] . ' ' . $ord['medecin_nom']) ?></td>
                                                        <td>
                                                            <?php if (strpos($ord['médicaments'], 'uploads/') === 0): ?>
                                                                <span class="text-muted">Fichier PDF</span>
                                                            <?php else: ?>
                                                                <?= htmlspecialchars($ord['médicaments']) ?>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if (strpos($ord['médicaments'], 'uploads/') === 0): ?>
                                                                <a href="<?= htmlspecialchars($ord['médicaments']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                                    <i class="fas fa-file-pdf"></i> Voir PDF
                                                                </a>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">Aucune ordonnance trouvée pour ce patient.</p>
                                <?php endif; ?>
                            </div>
                            <hr>
                            <!-- SECTION AMÉLIORÉE : Examens -->
                            <div class="mb-4">
                                <h4><i class="fas fa-file-medical"></i> Examens et Notes</h4>
                                <?php if (!empty($dossier_patient['examens'])): ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Type d'examen</th>
                                                    <th>Résultat</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($dossier_patient['examens'] as $exam): ?>
                                                    <tr>
                                                        <td><?= date('d/m/Y', strtotime($exam['date_examen'])) ?></td>
                                                        <td><?= htmlspecialchars($exam['type_examen']) ?></td>
                                                        <td>
                                                            <?php if (strpos($exam['résultat'], 'uploadsExamen/') === 0): ?>
                                                                <span class="text-muted">Fichier joint</span>
                                                            <?php else: ?>
                                                                <?= htmlspecialchars($exam['résultat']) ?>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if (strpos($exam['résultat'], 'uploadsExamen/') === 0): ?>
                                                                <?php
                                                                $file_extension = strtolower(pathinfo($exam['résultat'], PATHINFO_EXTENSION));
                                                                if ($file_extension === 'pdf'): ?>
                                                                    <a href="<?= htmlspecialchars($exam['résultat']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                                        <i class="fas fa-file-pdf"></i> Voir PDF
                                                                    </a>
                                                                <?php elseif (in_array($file_extension, ['jpg', 'jpeg', 'png'])): ?>
                                                                    <a href="<?= htmlspecialchars($exam['résultat']) ?>" target="_blank" class="btn btn-sm btn-outline-success">
                                                                        <i class="fas fa-image"></i> Voir Image
                                                                    </a>
                                                                <?php endif; ?>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">Aucun examen trouvé pour ce patient.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Modal for Add Patient -->
        <div class="modal fade" id="addPatientModal" tabindex="-1" aria-labelledby="addPatientModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addPatientModalLabel"><i class="fas fa-user-plus"></i> Ajouter un Patient</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="post" action="?action=add_patient">
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="nom" class="form-label">Nom *</label>
                                        <input type="text" class="form-control" id="nom" name="nom" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="prenom" class="form-label">Prénom *</label>
                                        <input type="text" class="form-control" id="prenom" name="prenom" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="date_naissance" class="form-label">Date de naissance *</label>
                                        <input type="date" class="form-control" id="date_naissance" name="date_naissance" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="sexe" class="form-label">Sexe *</label>
                                        <select class="form-control" id="sexe" name="sexe" required>
                                            <option value="">-- Sélectionner --</option>
                                            <option value="Homme">Homme</option>
                                            <option value="Femme">Femme</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email *</label>
                                        <input type="email" class="form-control" id="email" name="email" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="telephone" class="form-label">Téléphone</label>
                                        <input type="tel" class="form-control" id="telephone" name="telephone">
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="mb-3">
                                        <label for="adresse" class="form-label">Adresse</label>
                                        <textarea class="form-control" id="adresse" name="adresse" rows="3"></textarea>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="mb-3">
                                        <label for="dossier_medical" class="form-label">Dossier médical</label>
                                        <textarea class="form-control" id="dossier_medical" name="dossier_medical" rows="4"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal for Edit Patient -->
        <div class="modal fade" id="editPatientModal" tabindex="-1" aria-labelledby="editPatientModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editPatientModalLabel"><i class="fas fa-edit"></i> Modifier un Patient</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="post" action="?action=update_patient">
                        <input type="hidden" id="edit_id_patient" name="id_patient">
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="edit_nom" class="form-label">Nom *</label>
                                        <input type="text" class="form-control" id="edit_nom" name="nom" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="edit_prenom" class="form-label">Prénom *</label>
                                        <input type="text" class="form-control" id="edit_prenom" name="prenom" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="edit_date_naissance" class="form-label">Date de naissance *</label>
                                        <input type="date" class="form-control" id="edit_date_naissance" name="date_naissance" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="edit_sexe" class="form-label">Sexe *</label>
                                        <select class="form-control" id="edit_sexe" name="sexe" required>
                                            <option value="">-- Sélectionner --</option>
                                            <option value="Homme">Homme</option>
                                            <option value="Femme">Femme</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="edit_email" class="form-label">Email *</label>
                                        <input type="email" class="form-control" id="edit_email" name="email" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="edit_telephone" class="form-label">Téléphone</label>
                                        <input type="tel" class="form-control" id="edit_telephone" name="telephone">
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="mb-3">
                                        <label for="edit_adresse" class="form-label">Adresse</label>
                                        <textarea class="form-control" id="edit_adresse" name="adresse" rows="3"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Mettre à jour</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal for Add RDV -->
        <div class="modal fade" id="addRdvModal" tabindex="-1" aria-labelledby="addRdvModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addRdvModalLabel"><i class="fas fa-calendar-plus"></i> Ajouter un Rendez-vous</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="post" action="?action=add_rdv">
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="patient_id" class="form-label">Patient *</label>
                                        <select class="form-control" id="patient_id" name="patient_id" required>
                                            <option value="">-- Sélectionner un patient --</option>
                                            <?php
                                            if ($patients_with_treatment) {
                                                $patients_with_treatment->data_seek(0);
                                                while ($p = $patients_with_treatment->fetch_assoc()): ?>
                                                    <option value="<?= $p['id_patient'] ?>"><?= htmlspecialchars($p['prenom'] . ' ' . $p['nom']) ?></option>
                                            <?php endwhile;
                                            } ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="medecin_id" class="form-label">Médecin *</label>
                                        <select class="form-control" id="medecin_id" name="medecin_id" required>
                                            <option value="">-- Sélectionner un médecin --</option>
                                            <?php
                                            if ($medecins) {
                                                $medecins->data_seek(0);
                                                while ($m = $medecins->fetch_assoc()): ?>
                                                    <option value="<?= $m['id_medecin'] ?>"><?= htmlspecialchars('Dr. ' . $m['prenom'] . ' ' . $m['nom']) ?></option>
                                            <?php endwhile;
                                            } ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="date_rdv" class="form-label">Date *</label>
                                        <input type="date" class="form-control" id="date_rdv" name="date_rdv" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="heure" class="form-label">Heure *</label>
                                        <input type="time" class="form-control" id="heure" name="heure" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="lieu" class="form-label">Lieu *</label>
                                        <input type="text" class="form-control" id="lieu" name="lieu" value="Clinique A" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="motif" class="form-label">Motif *</label>
                                        <textarea class="form-control" id="motif" name="motif" rows="3" required></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                            <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Enregistrer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal for Add Exam -->
        <div class="modal fade" id="addExamModal" tabindex="-1" aria-labelledby="addExamModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addExamModalLabel"><i class="fas fa-notes-medical"></i> Ajouter un Examen</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="post" action="?action=add_exam_note" enctype="multipart/form-data">
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="exam_patient_id" class="form-label">Patient *</label>
                                        <select class="form-control" id="exam_patient_id" name="patient_id" required>
                                            <option value="">-- Sélectionner un patient --</option>
                                            <?php
                                            if ($patients_with_treatment) {
                                                $patients_with_treatment->data_seek(0);
                                                while ($p = $patients_with_treatment->fetch_assoc()): ?>
                                                    <option value="<?= $p['id_patient'] ?>"><?= htmlspecialchars($p['prenom'] . ' ' . $p['nom']) ?></option>
                                            <?php endwhile;
                                            } ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="type_examen" class="form-label">Type d'examen *</label>
                                        <input type="text" class="form-control" id="type_examen" name="type_examen" required>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="mb-3">
                                        <label for="exam_file" class="form-label">Fichier d'examen *</label>
                                        <input type="file" class="form-control" id="exam_file" name="exam_file" accept=".pdf,.jpg,.jpeg,.png" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                            <button type="submit" class="btn btn-info"><i class="fas fa-save"></i> Enregistrer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal for Add Hospitalisation -->
        <div class="modal fade" id="addHospitalisationModal" tabindex="-1" aria-labelledby="addHospitalisationModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addHospitalisationModalLabel"><i class="fas fa-procedures"></i> Ajouter une Hospitalisation</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="post" action="?action=add_hospitalisation">
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="hosp_patient_id" class="form-label">Patient *</label>
                                        <select class="form-control" id="hosp_patient_id" name="id_patient" required>
                                            <option value="">-- Sélectionner un patient --</option>
                                            <?php
                                            if ($all_patients) {
                                                $all_patients->data_seek(0);
                                                while ($p = $all_patients->fetch_assoc()): ?>
                                                    <option value="<?= $p['id_patient'] ?>"><?= htmlspecialchars($p['prenom'] . ' ' . $p['nom']) ?></option>
                                            <?php endwhile;
                                            } ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="service" class="form-label">Service *</label>
                                        <input type="text" class="form-control" id="service" name="service" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="date_entree" class="form-label">Date d'entrée *</label>
                                        <input type="date" class="form-control" id="date_entree" name="date_entree" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="date_sortie" class="form-label">Date de sortie</label>
                                        <input type="date" class="form-control" id="date_sortie" name="date_sortie">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>


        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
            let currentSection = 'dashboard';
            let previousSection = '';

            function showSection(sectionName, patientId = null) {
                // Hide all sections
                const sections = document.querySelectorAll('.content-section');
                sections.forEach(section => {
                    section.style.display = 'none';
                });

                // Show selected section
                const targetSection = document.getElementById(sectionName + 'Section');
                if (targetSection) {
                    targetSection.style.display = 'block';
                }

                // Update sidebar active state
                const sidebarLinks = document.querySelectorAll('.sidebar ul li a');
                sidebarLinks.forEach(link => {
                    link.classList.remove('active');
                });

                const activeLink = document.querySelector(`[onclick="showSection('${sectionName}')"]`);
                if (activeLink) {
                    activeLink.classList.add('active');
                }

                // Show/hide back button
                const backButton = document.getElementById('backButton');
                if (sectionName === 'dashboard') {
                    backButton.style.display = 'none';
                    previousSection = '';
                } else {
                    backButton.style.display = 'flex';
                    previousSection = currentSection;
                }

                currentSection = sectionName;
            }

            function goBack() {
                if (previousSection) {
                    showSection(previousSection);
                } else {
                    showSection('dashboard');
                }
            }

            function openModal(modalId) {
                const modal = new bootstrap.Modal(document.getElementById(modalId));
                modal.show();
            }

            function openEditPatientModal(id, nom, prenom, date_naissance, sexe, adresse, telephone, email) {
                document.getElementById('edit_id_patient').value = id;
                document.getElementById('edit_nom').value = nom;
                document.getElementById('edit_prenom').value = prenom;
                document.getElementById('edit_date_naissance').value = date_naissance;
                document.getElementById('edit_sexe').value = sexe;
                document.getElementById('edit_adresse').value = adresse;
                document.getElementById('edit_telephone').value = telephone;
                document.getElementById('edit_email').value = email;
                openModal('editPatientModal');
            }

            function cancelAppointment(rdvId) {
                Swal.fire({
                    title: 'Annuler le rendez-vous?',
                    text: "Cette action ne peut pas être annulée!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#e74c3c',
                    cancelButtonColor: '#95a5a6',
                    confirmButtonText: 'Oui, annuler!',
                    cancelButtonText: 'Retour'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = `?action=cancel_rdv&rdv_id=${rdvId}`;
                    }
                });
            }

            function deleteHospitalisation(id_hospitalisation) {
                Swal.fire({
                    title: 'Supprimer l\'hospitalisation?',
                    text: "Cette action ne peut pas être annulée!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#e74c3c',
                    cancelButtonColor: '#95a5a6',
                    confirmButtonText: 'Oui, supprimer!',
                    cancelButtonText: 'Annuler'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Redirect directly instead of using fetch, as PHP now handles the redirect
                        window.location.href = `?action=delete_hospitalisation&id=${id_hospitalisation}`;
                    }
                });
            }

            function confirmLogout() {
                Swal.fire({
                    title: 'Déconnexion',
                    text: "Êtes-vous sûr de vouloir vous déconnecter?",
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#3498db',
                    cancelButtonColor: '#95a5a6',
                    confirmButtonText: 'Oui, me déconnecter',
                    cancelButtonText: 'Annuler'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = 'deconnexion.php';
                    }
                });
            }

            // Search functionality
            function setupSearch(searchInputId, tableId) {
                const searchInput = document.getElementById(searchInputId);
                const table = document.getElementById(tableId);

                if (searchInput && table) {
                    searchInput.addEventListener('input', function() {
                        const filter = this.value.toLowerCase();
                        const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

                        for (let i = 0; i < rows.length; i++) {
                            const row = rows[i];
                            const cells = row.getElementsByTagName('td');
                            let found = false;

                            for (let j = 0; j < cells.length - 1; j++) {
                                if (cells[j].textContent.toLowerCase().indexOf(filter) > -1) {
                                    found = true;
                                    break;
                                }
                            }

                            row.style.display = found ? '' : 'none';
                        }
                    });
                }
            }

            document.addEventListener('DOMContentLoaded', () => {
                const urlParams = new URLSearchParams(window.location.search);
                const action = urlParams.get('action');
                const patientId = urlParams.get('id_patient');

                if (action === 'dossier' && patientId) {
                    showSection('dossierPatient');
                } else {
                    showSection('dashboard');
                }

                // Animation on load
                document.querySelectorAll('.stat-card, .action-card').forEach((card, index) => {
                    card.style.animationDelay = `${0.1 * index}s`;
                });

                // Setup search functionality
                setupSearch('patientsSearch', 'patientsTable');
                setupSearch('rdvSearch', 'rdvTable');
                setupSearch('hospitalisationSearch', 'hospitalisationTable');
            });
        </script>
</body>

</html>

<?php
$conn->close();
?>