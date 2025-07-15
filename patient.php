<?php
session_start();

// Restrict access to patients only
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "patient") {
    header("Location: connection.php");
    exit;
}

$conn = new mysqli("localhost", "root", "", "gestion_cabinet_medical");
$conn->set_charset("utf8");

$message = "";
$msg_type = "";
$active_section = $_GET["section"] ?? "dashboard";

// Fetch patient data
$stmt = $conn->prepare("SELECT * FROM patient WHERE id_patient = ?");
$stmt->bind_param("i", $id_patient);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
$stmt->close();


// Fetch upcoming rendezvous
$upcoming_rdv_query = "
    SELECT r.id_rdv, r.date_rdv, r.heure, r.motif, r.lieu, r.statut,
           m.prenom AS medecin_prenom, m.nom AS medecin_nom
    FROM rendezvous r
    JOIN traitement t ON r.id_traitement = t.id_traitement
    JOIN medecin m ON t.id_medecin = m.id_medecin
    WHERE t.id_patient = ? 
      AND r.date_rdv >= CURDATE()
      AND r.statut != 'annule'
    ORDER BY r.date_rdv ASC, r.heure ASC
";

$upcoming_rendezvous = [];
$stmt = $conn->prepare($upcoming_rdv_query);
if ($stmt) {
    $stmt->bind_param("i", $id_patient);
    $stmt->execute();
    $upcoming_rendezvous = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Fetch latest prescriptions
$ordonnances_query = "
    SELECT o.id_ordonnance, o.date_ordonnance AS date, o.médicaments AS description,
           m.prenom AS medecin_prenom, m.nom AS medecin_nom
    FROM ordonnance o
    JOIN traitement t ON o.id_traitement = t.id_traitement
    JOIN medecin m ON t.id_medecin = m.id_medecin
    WHERE t.id_patient = ?
    ORDER BY o.date_ordonnance DESC
    LIMIT 5
";

$ordonnances = [];
$stmt = $conn->prepare($ordonnances_query);
if ($stmt) {
    $stmt->bind_param("i", $id_patient);
    $stmt->execute();
    $ordonnances = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Fetch latest exams
$examens_query = "
    SELECT e.id_examen, e.date_examen AS date, e.type_examen, e.résultat AS description,
           m.prenom AS medecin_prenom, m.nom AS medecin_nom
    FROM examen e
    JOIN traitement t ON e.id_traitement = t.id_traitement
    JOIN medecin m ON t.id_medecin = m.id_medecin
    WHERE t.id_patient = ?
    ORDER BY e.date_examen DESC
    LIMIT 5
";

$examens = [];
$stmt = $conn->prepare($examens_query);
if ($stmt) {
    $stmt->bind_param("i", $id_patient);
    $stmt->execute();
    $examens = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Fetch dossier medical
$dossier = 'Aucune information disponible.';
$dossier_query = "SELECT dossier_medical FROM patient WHERE id_patient = ?";
$stmt = $conn->prepare($dossier_query);
if ($stmt) {
    $stmt->bind_param("i", $id_patient);
    $stmt->execute();
    $dossier_row = $stmt->get_result()->fetch_assoc();
    if ($dossier_row) {
        $dossier = $dossier_row['dossier_medical'] ?? 'Aucune information disponible.';
    }
    $stmt->close();
}

// Fetch all prescriptions for medical record
$dossier_ordonnances_query = "
    SELECT o.date_ordonnance, o.médicaments, m.nom AS medecin_nom, m.prenom AS medecin_prenom
    FROM ordonnance o
    JOIN traitement t ON o.id_traitement = t.id_traitement
    JOIN medecin m ON t.id_medecin = m.id_medecin
    WHERE t.id_patient = ?
    ORDER BY o.date_ordonnance DESC
";

$dossier_ordonnances = [];
$stmt = $conn->prepare($dossier_ordonnances_query);
if ($stmt) {
    $stmt->bind_param("i", $id_patient);
    $stmt->execute();
    $dossier_ordonnances = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Fetch all exams for medical record
$dossier_examens_query = "
    SELECT e.date_examen, e.type_examen, e.résultat, m.nom AS medecin_nom, m.prenom AS medecin_prenom
    FROM examen e
    JOIN traitement t ON e.id_traitement = t.id_traitement
    JOIN medecin m ON t.id_medecin = m.id_medecin
    WHERE t.id_patient = ?
    ORDER BY e.date_examen DESC
";

$dossier_examens = [];
$stmt = $conn->prepare($dossier_examens_query);
if ($stmt) {
    $stmt->bind_param("i", $id_patient);
    $stmt->execute();
    $dossier_examens = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Fetch hospitalizations
$hospitalisations_query = "
    SELECT h.id_hospitalisation, h.date_entree, h.date_sortie, h.service,
           m.prenom AS medecin_prenom, m.nom AS medecin_nom
    FROM hospitalisation h
    JOIN traitement t ON h.id_traitement = t.id_traitement
    JOIN medecin m ON t.id_medecin = m.id_medecin
    WHERE t.id_patient = ?
    ORDER BY h.date_entree DESC
";

$hospitalisations = [];
$stmt = $conn->prepare($hospitalisations_query);
if ($stmt) {
    $stmt->bind_param("i", $id_patient);
    $stmt->execute();
    $hospitalisations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Liste des médecins for rendezvous creation and hospitalization
$medecins = $conn->query("SELECT id_medecin, nom, prenom, spécialité FROM medecin ORDER BY nom, prenom");

// Handle profile modification
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["modifier_profil"]) && $active_section === "modify-profile") {
    $nom = trim($_POST["nom"] ?? "");
    $prenom = trim($_POST["prenom"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $telephone = trim($_POST["telephone"] ?? "");
    $adresse = trim($_POST["adresse"] ?? "");
    $sexe = $_POST["sexe"] ?? "";

    if (empty($nom) || empty($prenom) || empty($email) || empty($telephone) || empty($sexe)) {
        $message = "error:Tous les champs obligatoires doivent être remplis.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "error:L'email n'est pas valide.";
    } elseif (!in_array($sexe, ['Homme', 'Femme'])) {
        $message = "error:Le sexe sélectionné est invalide.";
    } else {
        $stmt = $conn->prepare("UPDATE patient SET nom=?, prenom=?, email=?, telephone=?, adresse=?, sexe=? WHERE id_patient=?");
        if ($stmt) {
            $stmt->bind_param("ssssssi", $nom, $prenom, $email, $telephone, $adresse, $sexe, $id_patient);
            if ($stmt->execute()) {
                $message = "success:Profil mis à jour avec succès.";
                // Refresh patient data
                $stmt->close();
                $stmt = $conn->prepare("SELECT * FROM patient WHERE id_patient = ?");
                $stmt->bind_param("i", $id_patient);
                $stmt->execute();
                $patient = $stmt->get_result()->fetch_assoc();
            } else {
                $message = "error:Erreur lors de la mise à jour du profil.";
            }
            $stmt->close();
        } else {
            $message = "error:Erreur de préparation de la requête.";
        }
    }
}

// Handle rendezvous creation
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["creer_rdv"]) && $active_section === "create-rdv") {
    $id_medecin = intval($_POST["id_medecin"] ?? 0);
    $date_rdv = $_POST["date_rdv"] ?? "";
    $heure = $_POST["heure"] ?? "";
    $motif = trim($_POST["motif"] ?? "");
    $lieu = trim($_POST["lieu"] ?? "Clinique A");

    if (!$id_medecin || !$date_rdv || !$heure) {
        $message = "error:Tous les champs sont obligatoires.";
    } else {
        $id_traitement = null;
        $check_traitement = $conn->prepare("SELECT id_traitement FROM traitement WHERE id_patient = ? AND id_medecin = ?");
        if ($check_traitement) {
            $check_traitement->bind_param("ii", $id_patient, $id_medecin);
            $check_traitement->execute();
            $traitement = $check_traitement->get_result()->fetch_assoc();

            if (!$traitement) {
                $insert_traitement = $conn->prepare("INSERT INTO traitement (id_patient, id_medecin, date_debut) VALUES (?, ?, CURDATE())");
                if ($insert_traitement) {
                    $insert_traitement->bind_param("ii", $id_patient, $id_medecin);
                    $insert_traitement->execute();
                    $id_traitement = $insert_traitement->insert_id;
                    $insert_traitement->close();
                }
            } else {
                $id_traitement = $traitement['id_traitement'] ?? null;
            }
            $check_traitement->close();
        }

        if (!$id_traitement) {
            $message = "error:Erreur lors de la création du traitement.";
        } else {
            $check = $conn->prepare("SELECT id_rdv FROM rendezvous WHERE id_traitement IN (SELECT id_traitement FROM traitement WHERE id_medecin = ?) AND date_rdv = ? AND heure = ?");
            if ($check) {
                $check->bind_param("iss", $id_medecin, $date_rdv, $heure);
                $check->execute();
                if ($check->get_result()->num_rows > 0) {
                    $message = "error:Créneau indisponible.";
                } else {
                    $insert = $conn->prepare("INSERT INTO rendezvous (id_traitement, date_rdv, heure, lieu, motif) VALUES (?, ?, ?, ?, ?)");
                    if ($insert) {
                        $insert->bind_param("issss", $id_traitement, $date_rdv, $heure, $lieu, $motif);
                        if ($insert->execute()) {
                            $message = "success:Rendez-vous créé avec succès.";
                            // Refresh upcoming rendezvous
                            $stmt = $conn->prepare($upcoming_rdv_query);
                            if ($stmt) {
                                $stmt->bind_param("i", $id_patient);
                                $stmt->execute();
                                $upcoming_rendezvous = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                                $stmt->close();
                            }
                        } else {
                            $message = "error:Erreur d'enregistrement.";
                        }
                        $insert->close();
                    } else {
                        $message = "error:Erreur de préparation de la requête.";
                    }
                }
                $check->close();
            } else {
                $message = "error:Erreur de vérification de disponibilité.";
            }
        }
    }
}

// Handle hospitalization creation
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["creer_hospitalisation"]) && $active_section === "hospitalisation") {
    $id_medecin_hosp = intval($_POST["id_medecin_hosp"] ?? 0);
    $date_entree = $_POST["date_entree"] ?? "";
    $date_sortie = $_POST["date_sortie"] ?? "";
    $service = trim($_POST["service"] ?? "");

    if (!$id_medecin_hosp || !$date_entree || empty($service)) {
        $message = "error:Tous les champs obligatoires (Médecin, Date d'entrée, Service) doivent être remplis pour l'hospitalisation.";
    } elseif (!empty($date_sortie) && strtotime($date_sortie) < strtotime($date_entree)) {
        $message = "error:La date de sortie ne peut pas être antérieure à la date d'entrée.";
    } else {
        $id_traitement_hosp = null;
        $check_traitement_hosp = $conn->prepare("SELECT id_traitement FROM traitement WHERE id_patient = ? AND id_medecin = ?");
        if ($check_traitement_hosp) {
            $check_traitement_hosp->bind_param("ii", $id_patient, $id_medecin_hosp);
            $check_traitement_hosp->execute();
            $traitement_hosp = $check_traitement_hosp->get_result()->fetch_assoc();

            if (!$traitement_hosp) {
                $insert_traitement_hosp = $conn->prepare("INSERT INTO traitement (id_patient, id_medecin, date_debut) VALUES (?, ?, CURDATE())");
                if ($insert_traitement_hosp) {
                    $insert_traitement_hosp->bind_param("ii", $id_patient, $id_medecin_hosp);
                    $insert_traitement_hosp->execute();
                    $id_traitement_hosp = $insert_traitement_hosp->insert_id;
                    $insert_traitement_hosp->close();
                }
            } else {
                $id_traitement_hosp = $traitement_hosp['id_traitement'] ?? null;
            }
            $check_traitement_hosp->close();
        }

        if (!$id_traitement_hosp) {
            $message = "error:Erreur lors de la création du traitement pour l'hospitalisation.";
        } else {
            $insert_hosp = $conn->prepare("INSERT INTO hospitalisation (id_traitement, date_entree, date_sortie, service) VALUES (?, ?, ?, ?)");
            if ($insert_hosp) {
                $insert_hosp->bind_param("isss", $id_traitement_hosp, $date_entree, $date_sortie, $service);
                if ($insert_hosp->execute()) {
                    $message = "success:Hospitalisation enregistrée avec succès.";
                    // Refresh hospitalizations
                    $stmt = $conn->prepare($hospitalisations_query);
                    if ($stmt) {
                        $stmt->bind_param("i", $id_patient);
                        $stmt->execute();
                        $hospitalisations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                        $stmt->close();
                    }
                } else {
                    $message = "error:Erreur lors de l'enregistrement de l'hospitalisation.";
                }
                $insert_hosp->close();
            } else {
                $message = "error:Erreur de préparation de la requête pour l'hospitalisation.";
            }
        }
    }
}

if ($message) {
    list($msg_type, $msg_text) = explode(':', $message, 2);
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Tableau de Bord Patient</title>
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

    .photo-preview {
      max-width: 100%;
      max-height: 300px;
      margin-top: 10px;
      border-radius: 8px;
      display: none;
    }

    .modal-image {
      max-width: 100%;
      max-height: 80vh;
      display: block;
      margin: 0 auto;
    }

    .photo-placeholder {
      width: 100px;
      height: 100px;
      background-color: #f0f0f0;
      border: 1px dashed #ccc;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 8px;
      color: #777;
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
      .table-container {
        padding: 1rem;
      }
      .dashboard-grid {
        grid-template-columns: 1fr;
      }
      .quick-actions {
        grid-template-columns: 1fr 1fr;
      }
    }

    @media (max-width: 576px) {
      .submit-btn {
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

    /* Style for back button */
    .back-button {
      position: fixed;
      top: 1.5rem;
      left: 310px; /* Adjust based on sidebar width */
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
      cursor: pointer;
    }

    .back-button:hover {
      transform: scale(1.1);
    }

    .sidebar.collapsed ~ .main-content .back-button {
      left: 120px; /* Adjust for collapsed sidebar */
    }

    .back-button.hidden {
      display: none;
    }
  </style>
</head>
<body data-theme="light">
  <div class="app-container">
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
      <div class="sidebar-brand animate__animated animate__fadeInDown">
        <i class="fas fa-heartbeat"></i> Cabinet Médical
      </div>
      <div class="sidebar-nav">
        <a href="?section=dashboard" class="nav-link <?= $active_section === 'dashboard' ? 'active' : '' ?>" aria-label="Tableau de Bord">
          <i class="fas fa-home"></i> <span>Accueil</span>
        </a>
        <a href="?section=create-rdv" class="nav-link <?= $active_section === 'create-rdv' ? 'active' : '' ?>" aria-label="Créer Rendez-vous">
          <i class="fas fa-calendar-alt"></i> <span>Créer RDV</span>
        </a>
        <a href="?section=modify-profile" class="nav-link <?= $active_section === 'modify-profile' ? 'active' : '' ?>" aria-label="Modifier Profil">
          <i class="fas fa-user-edit"></i> <span>Modifier Profil</span>
        </a>
        <a href="?section=upcoming-rdv" class="nav-link <?= $active_section === 'upcoming-rdv' ? 'active' : '' ?>" aria-label="Prochains RDV">
          <i class="fas fa-calendar-check"></i> <span>Prochains RDV</span>
        </a>
        <a href="?section=prescriptions" class="nav-link <?= $active_section === 'prescriptions' ? 'active' : '' ?>" aria-label="Ordonnances">
          <i class="fas fa-pills"></i> <span>Ordonnances</span>
        </a>
        <a href="?section=examens" class="nav-link <?= $active_section === 'examens' ? 'active' : '' ?>" aria-label="Examens">
          <i class="fas fa-microscope"></i> <span>Examens</span>
        </a>
        <a href="?section=hospitalisation" class="nav-link <?= $active_section === 'hospitalisation' ? 'active' : '' ?>" aria-label="Hospitalisation">
          <i class="fas fa-hospital"></i> <span>Hospitalisation</span>
        </a>
        <a href="?section=dossier" class="nav-link <?= $active_section === 'dossier' ? 'active' : '' ?>" aria-label="Dossier">
          <i class="fas fa-notes-medical"></i> <span>Dossier</span>
        </a>
        <a href="deconnexion.php" class="nav-link" aria-label="Déconnexion">
          <i class="fas fa-sign-out-alt"></i> <span>Déconnexion</span>
        </a>
      </div>
    </div>

    <!-- Sidebar Toggle -->
    <button class="sidebar-toggle" onclick="toggleSidebar()">
      <i class="fas fa-bars"></i>
    </button>

    <!-- Back Button -->
    <button class="back-button <?= $active_section === 'dashboard' ? 'hidden' : '' ?>" onclick="history.back()" aria-label="Retour">
      <i class="fas fa-arrow-left"></i>
    </button>

    <!-- Main Content -->
    <div class="main-content">
      <!-- Theme Toggle -->
      <button class="theme-toggle" onclick="toggleTheme()" aria-label="Toggle theme">
        <i class="fas fa-sun"></i>
      </button>

      <!-- Header -->
      <div class="header animate__animated animate__fadeIn">
        <div class="header-info flex-grow-1">
          <h1 class="header-title">
            <i class="fas fa-user me-2"></i>
            Bienvenue, <?= htmlspecialchars(($patient['prenom'] ?? '') . ' ' . ($patient['nom'] ?? '')) ?>
          </h1>
        </div>
      </div>

      <?php if (!empty($message)): ?>
        <div class="message message-<?= $msg_type ?> active"><?= htmlspecialchars($msg_text) ?></div>
      <?php endif; ?>

      <!-- Page d'accueil -->
      <div id="dashboard-section" class="section-container <?= $active_section === 'dashboard' ? '' : 'd-none' ?>">
        <h2 class="mb-4"><i class="fas fa-home me-2"></i> Tableau de Bord</h2>
        
        <div class="dashboard-grid">
          <!-- Carte Statistiques RDV -->
          <div class="dashboard-card stats-card">
            <div class="card-icon">
              <i class="fas fa-calendar-check"></i>
            </div>
            <div class="stat-value"><?= count($upcoming_rendezvous) ?></div>
            <div class="stat-label">Rendez-vous à venir</div>
          </div>
          
          <!-- Carte Ordonnances -->
          <div class="dashboard-card stats-card" style="background: linear-gradient(135deg, var(--accent), var(--accent-dark));">
            <div class="card-icon">
              <i class="fas fa-pills"></i>
            </div>
            <div class="stat-value"><?= count($ordonnances) ?></div>
            <div class="stat-label">Ordonnances récentes</div>
          </div>
          
          <!-- Carte Examens -->
          <div class="dashboard-card stats-card" style="background: linear-gradient(135deg, #f68b5c, #d96d28);">
            <div class="card-icon">
              <i class="fas fa-microscope"></i>
            </div>
            <div class="stat-value"><?= count($examens) ?></div>
            <div class="stat-label">Examens récents</div>
          </div>

          <!-- Carte Hospitalisation -->
          <div class="dashboard-card stats-card" style="background: linear-gradient(135deg, #8b5cf6, #6d28d9);">
            <div class="card-icon">
              <i class="fas fa-hospital"></i>
            </div>
            <div class="stat-value"><?= count($hospitalisations) ?></div>
            <div class="stat-label">Hospitalisations</div>
          </div>

          <!-- Carte Dossier -->
          <div class="dashboard-card stats-card" style="background: linear-gradient(135deg, #5cc6f6, #28a7d9);">
            <div class="card-icon">
              <i class="fas fa-file-medical"></i>
            </div>
            <div class="stat-value">1</div>
            <div class="stat-label">Dossier médical</div>
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
            <i class="fas fa-prescription-bottle-alt"></i>
            <span>Mes ordonnances</span>
          </a>
          <a href="?section=examens" class="quick-action-btn">
            <i class="fas fa-microscope"></i>
            <span>Mes examens</span>
          </a>
          <a href="?section=hospitalisation" class="quick-action-btn">
            <i class="fas fa-hospital"></i>
            <span>Hospitalisation</span>
          </a>
          <a href="?section=modify-profile" class="quick-action-btn">
            <i class="fas fa-user-cog"></i>
            <span>Mon profil</span>
          </a>
          <a href="?section=dossier" class="quick-action-btn">
            <i class="fas fa-file-medical-alt"></i>
            <span>Mon dossier</span>
          </a>
        </div>

        <!-- Prochain rendez-vous -->
        <?php if (!empty($upcoming_rendezvous)): ?>
          <div class="dashboard-card mt-4">
            <h3><i class="fas fa-bell me-2"></i> Votre prochain rendez-vous</h3>
            <div class="alert alert-primary">
              <div class="d-flex align-items-center">
                <i class="fas fa-calendar-day fa-2x me-3"></i>
                <div>
                  <h5 class="mb-1">Dr. <?= htmlspecialchars(($upcoming_rendezvous[0]['medecin_prenom'] ?? '') . ' ' . ($upcoming_rendezvous[0]['medecin_nom'] ?? '')) ?></h5>
                  <p class="mb-1">
                    <i class="fas fa-clock me-1"></i> 
                    <?= date('d/m/Y', strtotime($upcoming_rendezvous[0]['date_rdv'] ?? '')) ?> à <?= substr($upcoming_rendezvous[0]['heure'] ?? '', 0, 5) ?>
                  </p>
                  <p class="mb-0"><i class="fas fa-map-marker-alt me-1"></i> <?= htmlspecialchars($upcoming_rendezvous[0]['lieu'] ?? '') ?></p>
                </div>
              </div>
            </div>
            <a href="?section=upcoming-rdv" class="btn btn-primary mt-2">
              <i class="fas fa-calendar-alt me-1"></i> Voir tous mes RDV
            </a>
          </div>
        <?php endif; ?>

        <!-- Conseils santé -->
        <div class="dashboard-card mt-4">
          <h3><i class="fas fa-lightbulb me-2"></i> Conseils santé</h3>
          <div class="alert alert-success">
            <h5><i class="fas fa-heartbeat me-2"></i> Bien-être quotidien</h5>
            <p>Prenez le temps de faire une activité physique régulière, même une simple marche de 30 minutes par jour peut faire une grande différence.</p>
          </div>
          <div class="alert alert-info">
            <h5><i class="fas fa-clock me-2"></i> Gestion du stress</h5>
            <p>Pratiquez des exercices de respiration profonde pour réduire le stress et améliorer votre concentration.</p>
          </div>
        </div>
      </div>

      <!-- Section Création RDV -->
      <div id="create-rdv-section" class="section-container <?= $active_section === 'create-rdv' ? '' : 'd-none' ?>">
        <h2 class="mb-4"><i class="fas fa-calendar-alt me-2"></i> Créer un Rendez-vous</h2>
        <form method="post">
          <div class="form-group">
            <label for="id_medecin">Médecin :</label>
            <select id="id_medecin" name="id_medecin" required>
              <option value="">-- Sélectionnez --</option>
              <?php while ($m = $medecins->fetch_assoc()): ?>
                <option value="<?= $m['id_medecin'] ?>">Dr. <?= htmlspecialchars(($m['prenom'] ?? '') . ' ' . ($m['nom'] ?? '')) ?> - <?= htmlspecialchars($m['spécialité'] ?? '') ?></option>
              <?php endwhile; $medecins->data_seek(0); ?>
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

      <!-- Section Modification Profil -->
      <div id="modify-profile-section" class="section-container <?= $active_section === 'modify-profile' ? '' : 'd-none' ?>">
        <h2 class="mb-4"><i class="fas fa-user-edit me-2"></i> Modifier Mon Profil</h2>
        <form method="post">
          <div class="form-group">
            <label for="nom">Nom :</label>
            <input type="text" id="nom" name="nom" value="<?= htmlspecialchars($patient['nom'] ?? '') ?>" required>
          </div>

          <div class="form-group">
            <label for="prenom">Prénom :</label>
            <input type="text" id="prenom" name="prenom" value="<?= htmlspecialchars($patient['prenom'] ?? '') ?>" required>
          </div>

          <div class="form-group">
            <label for="email">Email :</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($patient['email'] ?? '') ?>" required>
          </div>

          <div class="form-group">
            <label for="telephone">Téléphone :</label>
            <input type="text" id="telephone" name="telephone" value="<?= htmlspecialchars($patient['telephone'] ?? '') ?>" required>
          </div>

          <div class="form-group">
            <label for="adresse">Adresse :</label>
            <textarea id="adresse" name="adresse"><?= htmlspecialchars($patient['adresse'] ?? '') ?></textarea>
          </div>

          <div class="form-group">
            <label for="sexe">Sexe :</label>
            <select id="sexe" name="sexe" required>
              <option value="Homme" <?= (($patient['sexe'] ?? '') === 'Homme') ? 'selected' : '' ?>>Homme</option>
              <option value="Femme" <?= (($patient['sexe'] ?? '') === 'Femme') ? 'selected' : '' ?>>Femme</option>
            </select>
          </div>

          <button type="submit" name="modifier_profil" class="submit-btn">Mettre à Jour</button>
        </form>
      </div>

      <!-- Section Ordonnances -->
      <div id="prescriptions-section" class="section-container <?= $active_section === 'prescriptions' ? '' : 'd-none' ?>">
        <h2 class="mb-4"><i class="fas fa-pills me-2"></i> Dernières Ordonnances</h2>
        <div class="table-container">
          <table>
            <thead>
              <tr>
                <th>Date</th>
                <th>Médecin</th>
                <th>Contenu</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($ordonnances)): ?>
                <tr><td colspan="3" style="text-align:center; padding: 1rem;">Aucune ordonnance récente</td></tr>
              <?php else: ?>
                <?php foreach ($ordonnances as $ordo): ?>
                  <tr>
                    <td><?= date('d/m/Y', strtotime($ordo['date'] ?? '')) ?></td>
                    <td>Dr. <?= htmlspecialchars(($ordo['medecin_prenom'] ?? '') . ' ' . ($ordo['medecin_nom'] ?? '')) ?></td>
                    <td>
                      <?php
                      $description = $ordo['description'] ?? '';
                      $isPdf = preg_match('/\.pdf$/i', $description);
                      if ($isPdf): ?>
                        <a href="<?= htmlspecialchars($description) ?>" 
                           target="_blank" 
                           class="btn btn-sm btn-primary">
                          <i class="fas fa-file-pdf me-1"></i> Voir l'ordonnance
                        </a>
                      <?php else: ?>
                        <?= nl2br(htmlspecialchars($description)) ?>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Section Examens -->
      <div id="examens-section" class="section-container <?= $active_section === 'examens' ? '' : 'd-none' ?>">
        <h2 class="mb-4"><i class="fas fa-microscope me-2"></i> Derniers Examens</h2>
        <div class="table-container">
          <table>
            <thead>
              <tr>
                <th>Date</th>
                <th>Médecin</th>
                <th>Type d'examen</th>
                <th>Résultat</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($examens)): ?>
                <tr><td colspan="4" style="text-align:center; padding: 1rem;">Aucun examen récent</td></tr>
              <?php else: ?>
                <?php foreach ($examens as $examen): ?>
                  <tr>
                    <td><?= date('d/m/Y', strtotime($examen['date'] ?? '')) ?></td>
                    <td>Dr. <?= htmlspecialchars(($examen['medecin_prenom'] ?? '') . ' ' . ($examen['medecin_nom'] ?? '')) ?></td>
                    <td><?= htmlspecialchars($examen['type_examen'] ?? '') ?></td>
                    <td><?= nl2br(htmlspecialchars($examen['description'] ?? '')) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Section Prochains RDV -->
      <div id="upcoming-rdv-section" class="section-container <?= $active_section === 'upcoming-rdv' ? '' : 'd-none' ?>">
        <h2 class="mb-4"><i class="fas fa-calendar-check me-2"></i> Prochains Rendez-vous</h2>
        <div class="table-container">
          <table>
            <thead>
              <tr>
                <th>Date</th>
                <th>Heure</th>
                <th>Médecin</th>
                <th>Lieu</th>
                <th>Motif</th>
                <th>Statut</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($upcoming_rendezvous)): ?>
                <tr><td colspan="6" style="text-align:center; padding: 1rem;">Aucun rendez-vous à venir</td></tr>
              <?php else: ?>
                <?php foreach ($upcoming_rendezvous as $rdv): ?>
                  <tr>
                    <td><?= date('d/m/Y', strtotime($rdv['date_rdv'] ?? '')) ?></td>
                    <td><?= substr($rdv['heure'] ?? '', 0, 5) ?></td>
                    <td>Dr. <?= htmlspecialchars(($rdv['medecin_prenom'] ?? '') . ' ' . ($rdv['medecin_nom'] ?? '')) ?></td>
                    <td><?= htmlspecialchars($rdv['lieu'] ?? '') ?></td>
                    <td><?= htmlspecialchars($rdv['motif'] ?? '') ?></td>
                    <td><?= htmlspecialchars($rdv['statut'] ?? '') ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Section Hospitalisation -->
      <div id="hospitalisation-section" class="section-container <?= $active_section === 'hospitalisation' ? '' : 'd-none' ?>">
        <h2 class="mb-4"><i class="fas fa-hospital me-2"></i> Hospitalisation</h2>
        <form method="post">
          <div class="form-group">
            <label for="id_medecin_hosp">Médecin responsable :</label>
            <select id="id_medecin_hosp" name="id_medecin_hosp" required>
              <option value="">-- Sélectionnez --</option>
              <?php $medecins->data_seek(0); // Reset pointer for this loop ?>
              <?php while ($m = $medecins->fetch_assoc()): ?>
                <option value="<?= $m['id_medecin'] ?>">Dr. <?= htmlspecialchars(($m['prenom'] ?? '') . ' ' . ($m['nom'] ?? '')) ?> - <?= htmlspecialchars($m['spécialité'] ?? '') ?></option>
              <?php endwhile; ?>
            </select>
          </div>

          <div class="form-group">
            <label for="date_entree">Date d'entrée :</label>
            <input type="date" id="date_entree" name="date_entree" required min="<?= date('Y-m-d') ?>">
          </div>

          <div class="form-group">
            <label for="date_sortie">Date de sortie (optionnel) :</label>
            <input type="date" id="date_sortie" name="date_sortie">
          </div>

          <div class="form-group">
            <label for="service">Service :</label>
            <input type="text" id="service" name="service" required placeholder="Ex: Cardiologie, Urgences">
          </div>

          <button type="submit" name="creer_hospitalisation" class="submit-btn">Enregistrer Hospitalisation</button>
        </form>

        <h3 class="mt-5 mb-4"><i class="fas fa-history me-2"></i> Historique des Hospitalisations</h3>
        <div class="table-container">
          <table>
            <thead>
              <tr>
                <th>Date d'entrée</th>
                <th>Date de sortie</th>
                <th>Service</th>
                <th>Médecin</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($hospitalisations)): ?>
                <tr><td colspan="4" style="text-align:center; padding: 1rem;">Aucune hospitalisation enregistrée</td></tr>
              <?php else: ?>
                <?php foreach ($hospitalisations as $hosp): ?>
                  <tr>
                    <td><?= date('d/m/Y', strtotime($hosp['date_entree'] ?? '')) ?></td>
                    <td><?= !empty($hosp['date_sortie']) ? date('d/m/Y', strtotime($hosp['date_sortie'])) : 'N/A' ?></td>
                    <td><?= htmlspecialchars($hosp['service'] ?? '') ?></td>
                    <td>Dr. <?= htmlspecialchars(($hosp['medecin_prenom'] ?? '') . ' ' . ($hosp['medecin_nom'] ?? '')) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Section Dossier Médical -->
      <div id="dossier-section" class="section-container <?= $active_section === 'dossier' ? '' : 'd-none' ?>">
        <h2 class="mb-4"><i class="fas fa-notes-medical me-2"></i> Dossier Médical</h2>
        <div class="table-container">
          <div class="mb-4">
            <h4><i class="fas fa-info-circle me-2"></i> Informations médicales</h4>
            <p class="p-3 bg-light rounded"><?= nl2br(htmlspecialchars($dossier)) ?></p>
          </div>
          
          <div class="mt-5">
            <h4><i class="fas fa-prescription-bottle-alt me-2"></i> Ordonnances</h4>
            <?php if (empty($dossier_ordonnances)): ?>
              <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i> Aucune ordonnance enregistrée dans votre dossier
              </div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-hover">
                  <thead class="table-primary">
                    <tr>
                      <th>Date</th>
                      <th>Médecin</th>
                      <th>Contenu</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($dossier_ordonnances as $ordo): ?>
                      <tr>
                        <td><?= date('d/m/Y', strtotime($ordo['date_ordonnance'] ?? '')) ?></td>
                        <td>Dr. <?= htmlspecialchars(($ordo['medecin_prenom'] ?? '') . ' ' . ($ordo['medecin_nom'] ?? '')) ?></td>
                        <td>
                          <?php
                          $medicaments = $ordo['médicaments'] ?? '';
                          $isPdf = preg_match('/\.pdf$/i', $medicaments);
                          if ($isPdf): ?>
                            <a href="<?= htmlspecialchars($medicaments) ?>" 
                               target="_blank" 
                               class="btn btn-sm btn-primary">
                              <i class="fas fa-file-pdf me-1"></i> Voir l'ordonnance
                            </a>
                          <?php else: ?>
                            <?= nl2br(htmlspecialchars($medicaments)) ?>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>

          <div class="mt-5">
            <h4><i class="fas fa-microscope me-2"></i> Examens</h4>
            <?php if (empty($dossier_examens)): ?>
              <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i> Aucun examen enregistré dans votre dossier
              </div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-hover">
                  <thead class="table-primary">
                    <tr>
                      <th>Date</th>
                      <th>Médecin</th>
                      <th>Type d'examen</th>
                      <th>Résultat</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($dossier_examens as $examen): ?>
                      <tr>
                        <td><?= date('d/m/Y', strtotime($examen['date_examen'] ?? '')) ?></td>
                        <td>Dr. <?= htmlspecialchars(($examen['medecin_prenom'] ?? '') . ' ' . ($examen['medecin_nom'] ?? '')) ?></td>
                        <td><?= htmlspecialchars($examen['type_examen'] ?? '') ?></td>
                        <td><?= nl2br(htmlspecialchars($examen['résultat'] ?? '')) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>

          <div class="mt-5">
            <h4><i class="fas fa-hospital me-2"></i> Hospitalisations</h4>
            <?php if (empty($hospitalisations)): ?>
              <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i> Aucune hospitalisation enregistrée dans votre dossier
              </div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-hover">
                  <thead class="table-primary">
                    <tr>
                      <th>Date d'entrée</th>
                      <th>Date de sortie</th>
                      <th>Service</th>
                      <th>Médecin</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($hospitalisations as $hosp): ?>
                      <tr>
                        <td><?= date('d/m/Y', strtotime($hosp['date_entree'] ?? '')) ?></td>
                        <td><?= !empty($hosp['date_sortie']) ? date('d/m/Y', strtotime($hosp['date_sortie'])) : 'N/A' ?></td>
                        <td><?= htmlspecialchars($hosp['service'] ?? '') ?></td>
                        <td>Dr. <?= htmlspecialchars(($hosp['medecin_prenom'] ?? '') . ' ' . ($hosp['medecin_nom'] ?? '')) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
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

      document.querySelectorAll('.section-container, .dashboard-card').forEach(el => observer.observe(el));

      // Show/hide back button based on active section
      const backButton = document.querySelector('.back-button');
      const activeSection = '<?= $active_section ?>';
      if (activeSection === 'dashboard') {
        backButton.classList.add('hidden');
      } else {
        backButton.classList.remove('hidden');
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
      document.querySelectorAll('.section-container').forEach(sec => sec.classList.add('d-none'));
      document.getElementById(sectionId).classList.remove('d-none');

      document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));
      const navLink = document.querySelector(`a[href="?section=${sectionId.replace('-section', '')}"]`);
      if (navLink) {
        navLink.classList.add('active');
      }

      // Update back button visibility
      const backButton = document.querySelector('.back-button');
      if (sectionId === 'dashboard-section') {
        backButton.classList.add('hidden');
      } else {
        backButton.classList.remove('hidden');
      }
    }

    // Event listener for sidebar navigation links
    document.querySelectorAll('.sidebar-nav a').forEach(link => {
        link.addEventListener('click', function(event) {
            event.preventDefault();
            const section = this.getAttribute('href').split('=')[1];
            if (section) {
                scrollToSection(section + '-section');
                history.pushState(null, '', this.getAttribute('href')); // Update URL without reloading
            } else if (this.getAttribute('href') === 'deconnexion.php') {
                window.location.href = 'deconnexion.php';
            }
        });
    });

    // Handle browser back/forward buttons
    window.addEventListener('popstate', function(event) {
        const urlParams = new URLSearchParams(window.location.search);
        const section = urlParams.get('section') || 'dashboard';
        scrollToSection(section + '-section');
    });

    // Initial scroll to active section
    <?php if ($active_section): ?>
      scrollToSection('<?= $active_section ?>-section');
    <?php endif; ?>
  </script>
</body>
</html>