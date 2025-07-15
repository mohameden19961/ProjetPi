<?php
session_start();

// Établir la connexion à la base de données
$conn = new mysqli("localhost", "root", "", "gestion_cabinet_medical");

// Vérifier la connexion
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fonction pour nettoyer les entrées
function sanitize_input($data)
{
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $conn->real_escape_string($data);
}

// Récupérer les données du formulaire depuis la session si elles existent
$formData = [];
if (isset($_SESSION['form_data'])) {
    $formData = $_SESSION['form_data'];
    unset($_SESSION['form_data']);
}

// Traitement de l'inscription
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register'])) {
    $errors = [];

    // Valider et nettoyer les entrées
    $prenom = sanitize_input($_POST['prenom'] ?? '');
    $nom = sanitize_input($_POST['nom'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $telephone = sanitize_input($_POST['telephone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = 'patient'; // Rôle par défaut

    // Validation des champs requis
    if (empty($prenom)) $errors[] = "Le prénom est requis";
    if (empty($nom)) $errors[] = "Le nom est requis";
    if (empty($email)) $errors[] = "L'email est requis";
    if (empty($password)) $errors[] = "Le mot de passe est requis";

    // Valider le format de l'email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Format d'email invalide";
    }

    // Vérifier si les mots de passe correspondent
    if ($password !== $confirm_password) {
        $errors[] = "Les mots de passe ne correspondent pas";
    }

    // Vérifier la force du mot de passe
    if (strlen($password) < 8) {
        $errors[] = "Le mot de passe doit contenir au moins 8 caractères";
    }

    // Gérer les rôles spéciaux (sans administrateur)
    if (isset($_POST['role']) && in_array($_POST['role'], ['medecin', 'assistant'])) {
        $role = $_POST['role'];
        $code = sanitize_input($_POST['auth_code'] ?? "");

        $valid_codes = [
            'medecin' => 'medecin456',
            'assistant' => 'assistant789'
        ];

        if (empty($code)) {
            $errors[] = "Un code d'autorisation est requis pour ce rôle";
        } elseif ($code !== $valid_codes[$role]) {
            $errors[] = "Code d'autorisation invalide pour ce rôle";

            $_SESSION['code_attempt'] = [
                'role' => $role,
                'code' => $code,
                'timestamp' => time()
            ];
        }
    }

    // Si aucune erreur, procéder à l'inscription
    if (empty($errors)) {
        // Vérifier si l'email existe déjà
        $stmt = $conn->prepare("SELECT id_utilisateur FROM utilisateur WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $errors[] = "Cet email est déjà utilisé";
        } else {
            // Hacher le mot de passe
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insérer le nouvel utilisateur
            $stmt = $conn->prepare("INSERT INTO utilisateur (nom, prenom, email, telephone, rôle) 
                                   VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $nom, $prenom, $email, $telephone, $role);

            // ... (le début de votre code reste inchangé)

            if ($stmt->execute()) {
                $new_user_id = $stmt->insert_id;

                // Créer le profil selon le rôle
                switch ($role) {
                    case 'medecin':
                        // Récupérer la spécialité depuis le formulaire
                        $specialite = sanitize_input($_POST['specialite_medecin'] ?? 'À définir');

                        // Préparer la requête pour insérer dans la table `medecin`
                        $stmt_medecin = $conn->prepare(
                            "INSERT INTO medecin (id_medecin, nom, prenom, spécialité, email, telephone) 
                 VALUES (?, ?, ?, ?, ?, ?)"
                        );
                        $stmt_medecin->bind_param("isssss", $new_user_id, $nom, $prenom, $specialite, $email, $telephone);
                        $stmt_medecin->execute();
                        break;

                    case 'assistant':
                        $departement = sanitize_input($_POST['specialite_assistant'] ?? 'À définir');
                        $stmt_assistant = $conn->prepare(
                            "INSERT INTO assistant (id_assistant, departement) 
                 VALUES (?, ?)"
                        );
                        $stmt_assistant->bind_param("is", $new_user_id, $departement);
                        $stmt_assistant->execute();
                        break;

                    // ==================================================================
                    // === BLOC AJOUTÉ POUR CORRIGER LE PROBLÈME ===
                    // ==================================================================
                    case 'patient':
                        // Préparer la requête pour insérer les informations dans la table `patient`
                        // Note : La table `patient` a des colonnes comme date_naissance, sexe, etc.
                        // Comme elles ne sont pas dans le formulaire, on insère les données essentielles.
                        $stmt_patient = $conn->prepare(
                            "INSERT INTO patient (id_patient, nom, prenom, email, telephone) 
                 VALUES (?, ?, ?, ?, ?)"
                        );
                        // Lier les 5 paramètres : l'ID, le nom, le prénom, l'email et le téléphone
                        $stmt_patient->bind_param("issss", $new_user_id, $nom, $prenom, $email, $telephone);

                        // Exécuter la requête pour la table `patient`
                        $stmt_patient->execute();
                        break;
                        // ==================================================================
                        // === FIN DU BLOC AJOUTÉ ===
                        // ==================================================================
                }

                // Créer les informations de connexion
                $stmt_connexion = $conn->prepare(
                    "INSERT INTO connexion (id_utilisateur, login, mot_de_passe) 
         VALUES (?, ?, ?)"
                );
                // Note: Il est préférable d'utiliser password_hash pour la table connexion aussi.
                // Mais pour rester cohérent avec votre code existant, j'utilise hash('sha256', ...).
                $hashed_password_db = hash('sha256', $password);
                $stmt_connexion->bind_param("iss", $new_user_id, $email, $hashed_password_db);
                $stmt_connexion->execute();

                $_SESSION['swal'] = [
                    'icon' => 'success',
                    'title' => 'Succès',
                    'text' => "Inscription réussie! Vous pouvez maintenant vous connecter"
                ];
                header("Location: connection.php");
                exit();
            } else {
                $errors[] = "Erreur lors de l'inscription: " . $stmt->error;
            }

            // ... (le reste de votre code reste inchangé)

        }
    }

    // S'il y avait des erreurs, les stocker en session avec les données du formulaire
    if (!empty($errors)) {
        $_SESSION['swal'] = [
            'icon' => 'error',
            'title' => 'Erreur',
            'text' => implode('<br>', $errors)
        ];

        // Stocker les données du formulaire pour les réafficher
        $_SESSION['form_data'] = [
            'prenom' => $prenom,
            'nom' => $nom,
            'email' => $email,
            'telephone' => $telephone,
            'role' => $role,
            'auth_code' => $code ?? '',
            'specialite_medecin' => $_POST['specialite_medecin'] ?? '',
            'specialite_assistant' => $_POST['specialite_assistant'] ?? ''
        ];

        // Rediriger vers le formulaire d'inscription
        header("Location: connection.php");
        exit();
    }
}

// Traitement de la connexion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $email = sanitize_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $_SESSION['swal'] = [
            'icon' => 'error',
            'title' => 'Erreur',
            'text' => "Email et mot de passe sont requis"
        ];
    } else {
        // Vérifier d'abord dans la table connexion
        $hashed_password = hash('sha256', $password);
        $stmt = $conn->prepare("SELECT c.id_utilisateur, u.* FROM connexion c 
                               JOIN utilisateur u ON c.id_utilisateur = u.id_utilisateur 
                               WHERE c.login = ? AND c.mot_de_passe = ?");
        $stmt->bind_param("ss", $email, $hashed_password);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // Définir les variables de session
            $_SESSION = [
                'user_id' => $user['id_utilisateur'],
                'email' => $user['email'],
                'role' => $user['rôle'],
                'prenom' => $user['prenom'],
                'nom' => $user['nom'],
                'medecin_id' => ($user['rôle'] === 'medecin') ? $user['id_utilisateur'] : null,
                'patient_id' => ($user['rôle'] === 'patient') ? $user['id_utilisateur'] : null,
                'assistant_id' => ($user['rôle'] === 'assistant') ? $user['id_utilisateur'] : null
            ];

            // Rediriger selon le rôle
            $redirect = match ($user['rôle']) {
                'admin' => 'dashboard_administrateur.php',
                'medecin' => 'medecin.php',
                'patient' => 'patient.php',
                'assistant' => 'assistant.php',
                default => 'connection.php'
            };

            header("Location: $redirect");
            exit();
        }

        // Si on arrive ici, la connexion a échoué
        $_SESSION['swal'] = [
            'icon' => 'error',
            'title' => 'Erreur',
            'text' => "Email ou mot de passe incorrect"
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Cabinet Médical</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        :root {
            --primary-color: #3a7bd5;
            --secondary-color: #00d2ff;
            --accent-color: #ff6b6b;
            --light-color: #f8f9fa;
            --dark-color: #2c3e50;
            --success-color: #4CAF50;
            --warning-color: #FFC107;
            --danger-color: #F44336;
            --border-radius: 10px;
            --box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
            --scrollbar-thumb: #3a7bd5;
            --scrollbar-track: #f1f1f1;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #333;
            line-height: 1.6;
        }

        .container {
            width: 100%;
            max-width: 900px;
            display: flex;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
            height: 95vh;
        }

        .presentation {
            flex: 1;
            background: linear-gradient(135deg, #1a2980, #26d0ce);
            color: white;
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
            min-width: 400px;
        }

        .presentation::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none"><polygon fill="rgba(255,255,255,0.05)" points="0,100 100,0 100,100"/></svg>');
            background-size: cover;
        }

        .presentation-content {
            position: relative;
            z-index: 2;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .logo {
            display: flex;
            align-items: center;
            margin-bottom: 2rem;
        }

        .logo i {
            font-size: 2.5rem;
            margin-right: 1rem;
            color: white;
        }

        .logo h1 {
            font-size: 1.8rem;
            font-weight: 600;
        }

        .presentation h2 {
            font-size: 2.5rem;
            margin-bottom: 1.5rem;
            line-height: 1.2;
        }

        .presentation p {
            margin-bottom: 1.5rem;
            font-size: 1.1rem;
        }

        .features {
            list-style: none;
            margin-top: 2rem;
        }

        .features li {
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
        }

        .features i {
            margin-right: 1rem;
            font-size: 1.5rem;
            color: white;
        }

        .forms-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            height: 100%;
            min-width: 400px;
        }

        .sticky-header {
            position: sticky;
            top: 0;
            background: white;
            z-index: 1000;
            padding: 1.5rem 3rem 0.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .forms-header {
            text-align: center;
            margin-bottom: 1rem;
        }

        .forms-header h2 {
            font-size: 2rem;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }

        .forms-header p {
            color: #666;
            margin-bottom: 0.5rem;
        }

        .tabs {
            display: flex;
            justify-content: center;
            margin-bottom: 0.5rem;
        }

        .tab-btn {
            padding: 0.8rem 2rem;
            background: none;
            border: none;
            font-size: 1.1rem;
            font-weight: 500;
            cursor: pointer;
            color: #666;
            border-bottom: 3px solid transparent;
            transition: var(--transition);
        }

        .tab-btn.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }

        .scrollable-container {
            flex: 1;
            overflow-y: auto;
            padding: 0 3rem 2rem;
            height: calc(100% - 160px);
            scrollbar-width: thin;
            scrollbar-color: var(--scrollbar-thumb) var(--scrollbar-track);
        }

        .scrollable-container::-webkit-scrollbar {
            width: 8px;
        }

        .scrollable-container::-webkit-scrollbar-track {
            background: var(--scrollbar-track);
            border-radius: 10px;
        }

        .scrollable-container::-webkit-scrollbar-thumb {
            background-color: var(--scrollbar-thumb);
            border-radius: 10px;
            border: 2px solid var(--scrollbar-track);
        }

        .specialite-field {
            margin-top: 1rem;
            animation: fadeIn 0.3s ease-out;
        }

        .form-container {
            display: none;
            padding-top: 1rem;
        }

        .form-container.active {
            display: block;
            animation: fadeIn 0.5s ease-out forwards;
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
            padding: 0.8rem 1rem;
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

        .password-container {
            position: relative;
        }

        .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #666;
        }

        .btn {
            width: 100%;
            padding: 0.9rem;
            border-radius: var(--border-radius);
            border: none;
            font-size: 1.1rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
            margin-top: 1rem;
        }

        .btn-outline:hover {
            background: var(--primary-color);
            color: white;
        }

        .error-message {
            color: var(--danger-color);
            margin-top: 0.5rem;
            font-size: 0.9rem;
        }

        .form-footer {
            text-align: center;
            margin-top: 1.5rem;
            color: #666;
        }

        .form-footer a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }

        .form-footer a:hover {
            text-decoration: underline;
        }

        .social-login {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .social-btn {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f5f5f5;
            border: none;
            cursor: pointer;
            transition: var(--transition);
        }

        .social-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .social-btn.facebook {
            color: #3b5998;
        }

        .social-btn.google {
            color: #dd4b39;
        }

        .social-btn.twitter {
            color: #1da1f2;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in {
            animation: fadeIn 0.5s ease-out forwards;
        }

        .role-selection {
            margin-bottom: 1.5rem;
            background: #f8f9fa;
            padding: 1rem;
            border-radius: var(--border-radius);
        }

        .role-options {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }

        .role-option {
            flex: 1;
            min-width: 100px;
            text-align: center;
            padding: 1rem;
            border: 2px solid #ddd;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
        }

        .role-option:hover {
            border-color: var(--primary-color);
            background: rgba(58, 123, 213, 0.05);
        }

        .role-option.selected {
            border-color: var(--primary-color);
            background: rgba(58, 123, 213, 0.1);
        }

        .role-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            color: var(--primary-color);
        }

        .authorization-code {
            margin-top: 1rem;
            display: none;
        }

        .authorization-code.active {
            display: block;
        }

        .inline-error {
            color: var(--danger-color);
            font-size: 0.85rem;
            margin-top: 0.25rem;
            display: none;
        }

        .show-error {
            display: block;
        }

        @media (max-width: 900px) {
            .container {
                flex-direction: column;
                max-width: 500px;
                height: auto;
                min-height: 100vh;
            }

            .presentation {
                display: none;
            }

            .role-options {
                flex-direction: column;
            }

            .sticky-header {
                padding: 1rem 1.5rem 0.5rem;
            }

            .scrollable-container {
                padding: 0 1.5rem 1.5rem;
                height: auto;
            }
        }

        @media (max-width: 500px) {
            .container {
                border-radius: 0;
                height: 100%;
            }

            .tab-btn {
                padding: 0.8rem 1.5rem;
            }

            .scrollable-container {
                padding: 0 1rem 1rem;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Left Section - Presentation -->
        <div class="presentation">
            <div class="presentation-content">
                <div class="logo">
                    <i class="fas fa-heartbeat"></i>
                    <h1>Gestion Cabinet Médical</h1>
                </div>
                <h2>Bienvenue sur notre plateforme médicale</h2>
                <p>Un système complet pour gérer vos consultations, patients et rendez-vous en toute simplicité.</p>
                <ul class="features">
                    <li>
                        <i class="fas fa-calendar-check"></i>
                        <span>Gestion des rendez-vous en temps réel</span>
                    </li>
                    <li>
                        <i class="fas fa-file-medical"></i>
                        <span>Dossiers patients sécurisés</span>
                    </li>
                    <li>
                        <i class="fas fa-user-md"></i>
                        <span>Interface adaptée pour médecins et assistants</span>
                    </li>
                    <li>
                        <i class="fas fa-bell"></i>
                        <span>Notifications automatiques</span>
                    </li>
                    <li>
                        <i class="fas fa-shield-alt"></i>
                        <span>Sécurité des données conforme RGPD</span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Right Section - Forms -->
        <div class="forms-container">
            <div class="sticky-header">
                <div class="forms-header">
                    <h2>Accès au système</h2>
                    <p>Connectez-vous ou créez un compte selon votre profil</p>
                </div>

                <div class="tabs">
                    <button class="tab-btn <?php echo (empty($formData)) ? 'active' : ''; ?>" id="login-tab">Connexion</button>
                    <button class="tab-btn <?php echo (!empty($formData)) ? 'active' : ''; ?>" id="register-tab">Inscription</button>
                </div>
            </div>

            <div class="scrollable-container">
                <!-- Login Form -->
                <div class="form-container <?php echo (empty($formData)) ? 'active' : ''; ?>" id="login-form">
                    <form method="POST" action="connection.php">
                        <div class="form-group">
                            <label for="login-email">Email</label>
                            <input type="email" id="login-email" name="email" class="form-control" placeholder="votre@email.com" required>
                        </div>

                        <div class="form-group">
                            <label for="login-password">Mot de passe</label>
                            <div class="password-container">
                                <input type="password" id="login-password" name="password" class="form-control" placeholder="Votre mot de passe" required>
                                <button type="button" class="toggle-password" onclick="togglePassword('login-password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="form-footer">
                            <a href="#">Mot de passe oublié ?</a>
                        </div>

                        <button type="submit" name="login" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt"></i> Connexion
                        </button>
                    </form>

                    <div class="social-login">
                        <button class="social-btn facebook">
                            <i class="fab fa-facebook-f"></i>
                        </button>
                        <button class="social-btn google">
                            <i class="fab fa-google"></i>
                        </button>
                        <button class="social-btn twitter">
                            <i class="fab fa-twitter"></i>
                        </button>
                    </div>
                </div>

                <!-- Register Form -->
                <div class="form-container <?php echo (!empty($formData)) ? 'active' : ''; ?>" id="register-form">
                    <form method="POST" action="connection.php" id="registration-form">
                        <div class="form-row" style="display: flex; gap: 1rem;">
                            <div class="form-group" style="flex: 1;">
                                <label for="register-prenom">Prénom</label>
                                <input type="text" id="register-prenom" name="prenom" class="form-control"
                                    placeholder="Votre prénom" required
                                    value="<?php echo isset($formData['prenom']) ? htmlspecialchars($formData['prenom']) : ''; ?>">
                            </div>

                            <div class="form-group" style="flex: 1;">
                                <label for="register-nom">Nom</label>
                                <input type="text" id="register-nom" name="nom" class="form-control"
                                    placeholder="Votre nom" required
                                    value="<?php echo isset($formData['nom']) ? htmlspecialchars($formData['nom']) : ''; ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="register-email">Email</label>
                            <input type="email" id="register-email" name="email" class="form-control"
                                placeholder="votre@email.com" required
                                value="<?php echo isset($formData['email']) ? htmlspecialchars($formData['email']) : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label for="register-phone">Téléphone</label>
                            <input type="tel" id="register-phone" name="telephone" class="form-control"
                                placeholder="Votre numéro de téléphone"
                                value="<?php echo isset($formData['telephone']) ? htmlspecialchars($formData['telephone']) : ''; ?>">
                        </div>

                        <div class="role-selection">
                            <p><strong>Sélectionnez votre rôle :</strong></p>
                            <div class="role-options">
                                <div class="role-option <?php echo (isset($formData['role']) && $formData['role'] === 'patient') ? 'selected' : ''; ?>"
                                    data-role="patient" onclick="selectRole('patient')">
                                    <div class="role-icon">
                                        <i class="fas fa-user-injured"></i>
                                    </div>
                                    <div>Patient</div>
                                </div>
                                <div class="role-option <?php echo (isset($formData['role']) && $formData['role'] === 'medecin') ? 'selected' : ''; ?>"
                                    data-role="medecin" onclick="selectRole('medecin')">
                                    <div class="role-icon">
                                        <i class="fas fa-user-md"></i>
                                    </div>
                                    <div>Médecin</div>
                                </div>
                                <div class="role-option <?php echo (isset($formData['role']) && $formData['role'] === 'assistant') ? 'selected' : ''; ?>"
                                    data-role="assistant" onclick="selectRole('assistant')">
                                    <div class="role-icon">
                                        <i class="fas fa-user-nurse"></i>
                                    </div>
                                    <div>Assistant</div>
                                </div>
                            </div>

                            <div class="authorization-code <?php echo (isset($formData['role']) && $formData['role'] !== 'patient') ? 'active' : ''; ?>"
                                id="authorization-code">
                                <div class="form-group">
                                    <label for="auth-code">Code d'autorisation</label>
                                    <input type="password" id="auth-code" name="auth_code" class="form-control"
                                        placeholder="Code fourni par l'administrateur"
                                        value="<?php echo isset($formData['auth_code']) ? htmlspecialchars($formData['auth_code']) : ''; ?>">
                                    <small class="error-message">Ce code est obligatoire pour les rôles spéciaux</small>
                                </div>
                            </div>

                            <!-- Champ spécialité pour médecin -->
                            <div class="specialite-field" id="specialite-medecin" style="display: none;">
                                <div class="form-group">
                                    <label for="specialite-medecin-input">Spécialité médicale</label>
                                    <input type="text" id="specialite-medecin-input" name="specialite_medecin" class="form-control"
                                        placeholder="Votre spécialité médicale"
                                        value="<?php echo isset($formData['specialite_medecin']) ? htmlspecialchars($formData['specialite_medecin']) : ''; ?>">
                                </div>
                            </div>

                            <!-- Champ département pour assistant -->
                            <div class="specialite-field" id="specialite-assistant" style="display: none;">
                                <div class="form-group">
                                    <label for="specialite-assistant-input">Département/Service</label>
                                    <input type="text" id="specialite-assistant-input" name="specialite_assistant" class="form-control"
                                        placeholder="Votre département ou service"
                                        value="<?php echo isset($formData['specialite_assistant']) ? htmlspecialchars($formData['specialite_assistant']) : ''; ?>">
                                </div>
                            </div>

                            <input type="hidden" id="selected-role" name="role"
                                value="<?php echo isset($formData['role']) ? htmlspecialchars($formData['role']) : 'patient'; ?>">
                        </div>

                        <div class="form-group">
                            <label for="register-password">Mot de passe</label>
                            <div class="password-container">
                                <input type="password" id="register-password" name="password" class="form-control"
                                    placeholder="Créer un mot de passe" required>
                                <button type="button" class="toggle-password" onclick="togglePassword('register-password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <small class="inline-error" id="password-error">Le mot de passe doit contenir au moins 8 caractères</small>
                        </div>

                        <div class="form-group">
                            <label for="register-confirm-password">Confirmer le mot de passe</label>
                            <div class="password-container">
                                <input type="password" id="register-confirm-password" name="confirm_password" class="form-control"
                                    placeholder="Confirmer votre mot de passe" required>
                                <button type="button" class="toggle-password" onclick="togglePassword('register-confirm-password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <small class="inline-error" id="confirm-password-error">Les mots de passe ne correspondent pas</small>
                        </div>

                        <button type="submit" name="register" class="btn btn-primary">
                            <i class="fas fa-user-plus"></i> S'inscrire
                        </button>

                        <button type="button" class="btn btn-outline" onclick="switchToLogin()">
                            <i class="fas fa-sign-in-alt"></i> J'ai déjà un compte
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script>
        // Switch between login and register forms
        document.getElementById('login-tab').addEventListener('click', function() {
            switchToLogin();
        });

        document.getElementById('register-tab').addEventListener('click', function() {
            document.getElementById('login-form').classList.remove('active');
            document.getElementById('register-form').classList.add('active');
            document.getElementById('login-tab').classList.remove('active');
            document.getElementById('register-tab').classList.add('active');
        });

        function switchToLogin() {
            document.getElementById('register-form').classList.remove('active');
            document.getElementById('login-form').classList.add('active');
            document.getElementById('register-tab').classList.remove('active');
            document.getElementById('login-tab').classList.add('active');
        }

        // Toggle password visibility
        function togglePassword(inputId) {
            const passwordInput = document.getElementById(inputId);
            const toggleButton = passwordInput.nextElementSibling;

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleButton.innerHTML = '<i class="fas fa-eye-slash"></i>';
            } else {
                passwordInput.type = 'password';
                toggleButton.innerHTML = '<i class="fas fa-eye"></i>';
            }
        }

        function selectRole(role) {
            // Reset all role options
            document.querySelectorAll('#register-form .role-option').forEach(option => {
                option.classList.remove('selected');
            });

            // Select the clicked role option
            document.querySelector(`#register-form .role-option[data-role="${role}"]`).classList.add('selected');

            // Update the hidden role input
            document.getElementById('selected-role').value = role;

            // Handle authorization code field
            const authCodeDiv = document.getElementById('authorization-code');
            const authCodeInput = document.getElementById('auth-code');

            // Hide all speciality fields first
            document.querySelectorAll('.specialite-field').forEach(field => {
                field.style.display = 'none';
            });

            if (role !== 'patient') {
                authCodeDiv.style.display = 'block';
                authCodeInput.required = true;

                // Show specific field based on role
                if (role === 'medecin') {
                    document.getElementById('specialite-medecin').style.display = 'block';
                } else if (role === 'assistant') {
                    document.getElementById('specialite-assistant').style.display = 'block';
                }
            } else {
                authCodeDiv.style.display = 'none';
                authCodeInput.required = false;
            }
        }

        // Initial call to set the correct state for registration
        <?php if (isset($formData['role'])): ?>
            selectRole('<?php echo $formData['role']; ?>');
        <?php else: ?>
            selectRole('patient');
        <?php endif; ?>

        // Display SweetAlert2 messages from PHP
        <?php if (isset($_SESSION['swal'])): ?>
            Swal.fire({
                icon: '<?php echo $_SESSION['swal']['icon']; ?>',
                title: '<?php echo $_SESSION['swal']['title']; ?>',
                html: '<?php echo $_SESSION['swal']['text']; ?>',
                confirmButtonColor: '#3a7bd5',
                timer: 3000,
                timerProgressBar: true
            });
            <?php unset($_SESSION['swal']); ?>
        <?php endif; ?>

        // Validation en temps réel pour les mots de passe
        const passwordInput = document.getElementById('register-password');
        const confirmPasswordInput = document.getElementById('register-confirm-password');
        const passwordError = document.getElementById('password-error');
        const confirmPasswordError = document.getElementById('confirm-password-error');

        function validatePasswords() {
            let isValid = true;

            // Vérifier la longueur du mot de passe
            if (passwordInput.value.length < 8) {
                passwordError.classList.add('show-error');
                isValid = false;
            } else {
                passwordError.classList.remove('show-error');
            }

            // Vérifier la correspondance des mots de passe
            if (passwordInput.value !== confirmPasswordInput.value) {
                confirmPasswordError.classList.add('show-error');
                isValid = false;
            } else {
                confirmPasswordError.classList.remove('show-error');
            }

            return isValid;
        }

        passwordInput.addEventListener('input', validatePasswords);
        confirmPasswordInput.addEventListener('input', validatePasswords);

        // Validation du formulaire avant soumission
        document.getElementById('registration-form').addEventListener('submit', function(e) {
            if (!validatePasswords()) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur de validation',
                    html: 'Veuillez corriger les erreurs dans le formulaire',
                    confirmButtonColor: '#3a7bd5'
                });
            }
        });
    </script>
</body>

</html>