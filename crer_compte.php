<?php
// Page de création de compte client
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

// Si déjà connecté, rediriger
if (isLoggedIn()) {
    if (isAdmin()) {
        redirect('admin/dashboard.php');
    } else {
        redirect('client/dashboard.php');
    }
}

// Récupérer les messages flash et les données précédentes
$errors = $_SESSION['flash_errors'] ?? [];
$old = $_SESSION['old'] ?? [];
unset($_SESSION['flash_errors'], $_SESSION['old']);
?>

<!doctype html>
<html lang="fr">

<head>

    <meta charset="utf-8">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <meta
        name="description"
        content="Création de compte client LOGITIX"
    >

    <meta
        name="author"
        content="LOGITIX"
    >

    <title>Créer un compte - LOGITIX</title>


    <!-- CSS FILES -->

    <link
        href="css/fonts.css"
        rel="stylesheet"
    >

    <link
        href="css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        href="css/bootstrap-icons.css"
        rel="stylesheet"
    >

    <link
        href="css/vegas.min.css"
        rel="stylesheet"
    >

    <link
        href="css/tooplate-barista.css"
        rel="stylesheet"
    >


    <style>

        :root {

            --logitix-navy: #01203F;

            --logitix-navy-light: #07355E;

            --logitix-navy-dark: #0A1A2E;

            --logitix-orange: #D2691E;

            --logitix-orange-dark: #A95013;

            --logitix-bg: #F3F5F7;

            --logitix-white: #FFFFFF;

            --logitix-text: #18222D;

            --logitix-muted: #727D88;

            --logitix-border: #DEE4EA;

            --logitix-soft: #F8FAFC;

            --logitix-danger: #DC3545;

            --logitix-success: #198754;

        }


        * {

            box-sizing: border-box;

        }


        html {

            scroll-behavior: smooth;

        }


        body.reservation-page {

            margin: 0;

            min-height: 100vh;

            background:

                radial-gradient(
                    circle at 10% 10%,
                    rgba(210, 105, 30, .05),
                    transparent 28%
                ),

                linear-gradient(
                    180deg,
                    #F7F8FA 0%,
                    #EFF2F5 100%
                );

            color: var(--logitix-text);

            font-family: 'Jost', sans-serif;

        }


        a {

            text-decoration: none;

        }



        /* =========================================
           BARRE SUPERIEURE
        ========================================== */

        .register-topbar {

            height: 72px;

            display: flex;

            align-items: center;

            background: #FFFFFF;

            border-bottom:
                1px solid rgba(1, 32, 63, .08);

            box-shadow:
                0 4px 20px rgba(1, 32, 63, .04);

        }


        .register-topbar-inner {

            width: 100%;

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 20px;

        }


        .topbar-brand {

            display: flex;

            align-items: center;

            gap: 12px;

        }


        .topbar-brand img {

            width: 48px;

            height: 48px;

            object-fit: contain;

        }


        .topbar-brand-text {

            line-height: 1;

        }


        .topbar-brand-name {

            display: block;

            color: var(--logitix-navy);

            font-size: 1.2rem;

            font-weight: 800;

            letter-spacing: .04em;

        }


        .topbar-brand-subtitle {

            display: block;

            margin-top: 5px;

            color: var(--logitix-muted);

            font-size: .7rem;

            text-transform: uppercase;

            letter-spacing: .08em;

        }


        .topbar-links {

            display: flex;

            align-items: center;

            gap: 10px;

        }


        .topbar-home {

            color: var(--logitix-navy);

            font-size: .84rem;

            font-weight: 600;

            padding: 9px 14px;

            border-radius: 9px;

            transition: .2s ease;

        }


        .topbar-home:hover {

            background:
                rgba(1, 32, 63, .05);

            color: var(--logitix-navy);

        }


        .topbar-login {

            display: inline-flex;

            align-items: center;

            gap: 7px;

            min-height: 40px;

            padding: 8px 15px;

            border-radius: 9px;

            background:
                var(--logitix-navy);

            color: #FFFFFF;

            font-size: .84rem;

            font-weight: 700;

            transition: .2s ease;

        }


        .topbar-login:hover {

            background:
                var(--logitix-navy-light);

            color: #FFFFFF;

            transform: translateY(-1px);

        }



        /* =========================================
           PAGE
        ========================================== */

        .register-page {

            padding:
                45px 0 55px;

        }


        .register-shell {

            overflow: hidden;

            background:
                var(--logitix-white);

            border:
                1px solid rgba(1, 32, 63, .08);

            border-radius: 24px;

            box-shadow:
                0 25px 70px rgba(1, 32, 63, .12);

        }


        .register-shell-row {

            min-height: 720px;

        }



        /* =========================================
           PARTIE GAUCHE
        ========================================== */

        .register-side {

            min-height: 100%;

            position: relative;

            overflow: hidden;

            display: flex;

            flex-direction: column;

            justify-content: space-between;

            padding: 42px;

            color: #FFFFFF;

            background:

                radial-gradient(
                    circle at 20% 15%,
                    rgba(210, 105, 30, .22),
                    transparent 28%
                ),

                radial-gradient(
                    circle at 85% 80%,
                    rgba(255, 255, 255, .08),
                    transparent 26%
                ),

                linear-gradient(
                    145deg,
                    var(--logitix-navy) 0%,
                    #063158 58%,
                    var(--logitix-navy-dark) 100%
                );

        }


        .register-side::before {

            content: "";

            position: absolute;

            width: 280px;

            height: 280px;

            border-radius: 50%;

            right: -130px;

            bottom: -110px;

            border:
                1px solid rgba(255, 255, 255, .08);

            box-shadow:

                0 0 0 35px
                rgba(255, 255, 255, .025),

                0 0 0 70px
                rgba(255, 255, 255, .015);

        }


        .register-side > * {

            position: relative;

            z-index: 2;

        }


        .side-logo {

            width: 165px;

            height: auto;

            object-fit: contain;

        }


        .side-badge {

            display: inline-flex;

            align-items: center;

            gap: 7px;

            width: fit-content;

            margin-bottom: 18px;

            padding: 7px 12px;

            border-radius: 50px;

            border:
                1px solid rgba(255, 255, 255, .14);

            background:
                rgba(255, 255, 255, .07);

            color:
                rgba(255, 255, 255, .78);

            font-size: .73rem;

            font-weight: 600;

        }


        .register-side h1 {

            max-width: 420px;

            margin: 0 0 16px;

            color: #FFFFFF;

            font-size:
                clamp(2rem, 4vw, 2.8rem);

            font-weight: 800;

            line-height: 1.12;

        }


        .register-side-description {

            max-width: 430px;

            margin: 0;

            color:
                rgba(255, 255, 255, .65);

            font-size: .93rem;

            line-height: 1.7;

        }


        .side-features {

            display: flex;

            flex-direction: column;

            gap: 13px;

            margin-top: 32px;

        }


        .side-feature {

            display: flex;

            align-items: center;

            gap: 11px;

            color:
                rgba(255, 255, 255, .82);

            font-size: .83rem;

        }


        .side-feature-icon {

            width: 35px;

            height: 35px;

            flex: 0 0 35px;

            display: grid;

            place-items: center;

            border-radius: 10px;

            background:
                rgba(210, 105, 30, .17);

            color:
                #F2AE7E;

        }


        .side-security {

            margin-top: 35px;

            padding: 15px;

            border-radius: 13px;

            border:
                1px solid rgba(255, 255, 255, .09);

            background:
                rgba(255, 255, 255, .045);

        }


        .side-security strong {

            display: block;

            color: #FFFFFF;

            font-size: .8rem;

            margin-bottom: 4px;

        }


        .side-security p {

            margin: 0;

            color:
                rgba(255, 255, 255, .50);

            font-size: .71rem;

            line-height: 1.5;

        }



        /* =========================================
           PARTIE FORMULAIRE
        ========================================== */

        .register-form-panel {

            padding:
                40px 45px 45px;

            background:
                #FFFFFF;

        }


        .form-header {

            margin-bottom: 27px;

        }


        .form-eyebrow {

            margin-bottom: 7px;

            color:
                var(--logitix-orange);

            font-size: .69rem;

            font-weight: 800;

            text-transform: uppercase;

            letter-spacing: .12em;

        }


        .form-header h2 {

            margin:
                0 0 7px;

            color:
                var(--logitix-navy);

            font-size:
                clamp(1.55rem, 3vw, 2rem);

            font-weight: 800;

        }


        .form-header p {

            margin: 0;

            color:
                var(--logitix-muted);

            font-size: .87rem;

            line-height: 1.55;

        }



        /* =========================================
           TITRES DE SECTION
        ========================================== */

        .form-section-title {

            display: flex;

            align-items: center;

            gap: 9px;

            margin:
                28px 0 16px;

            padding-bottom: 10px;

            color:
                var(--logitix-navy);

            border-bottom:
                1px solid #E8ECEF;

            font-size: .79rem;

            font-weight: 800;

            letter-spacing: .07em;

            text-transform: uppercase;

        }


        .form-section-title::before {

            content: "";

            width: 4px;

            height: 17px;

            flex: 0 0 4px;

            border-radius: 10px;

            background:
                var(--logitix-orange);

        }


        .form-section-title:first-of-type {

            margin-top: 0;

        }



        /* =========================================
           CHAMPS
        ========================================== */

        .field-icon {

            position: relative;

        }


        .field-icon i {

            position: absolute;

            left: 14px;

            top: 50%;

            z-index: 2;

            transform:
                translateY(-50%);

            color: #7D8995;

            font-size: .94rem;

            pointer-events: none;

        }


        .field-icon .form-control,
        .field-icon select.form-control {

            padding-left:
                40px !important;

        }


        .booking-form .form-control {

            min-height: 49px;

            border:
                1px solid
                var(--logitix-border) !important;

            border-radius:
                10px !important;

            background:
                #FFFFFF !important;

            color:
                var(--logitix-text) !important;

            box-shadow:
                none !important;

            font-size:
                .89rem;

            transition:
                border-color .2s ease,
                box-shadow .2s ease,
                background-color .2s ease;

        }


        .booking-form .form-control::placeholder {

            color:
                #99A2AB;

        }


        .booking-form .form-control:focus {

            background:
                #FFFFFF !important;

            border-color:
                rgba(1, 32, 63, .48) !important;

            box-shadow:
                0 0 0 3px
                rgba(1, 32, 63, .055)
                !important;

        }


        .booking-form select.form-control {

            cursor: pointer;

        }


        .field-help {

            margin-top: 5px;

            color:
                var(--logitix-muted);

            font-size: .72rem;

        }



        /* =========================================
           INFORMATIONS PROFESSIONNELLES
        ========================================== */

        #bloc-entreprise {

            display: none;

            margin-top:
                16px;

            padding:
                19px;

            border:
                1px solid #DFE5EA;

            border-radius:
                13px;

            background:
                #F8FAFC;

        }


        #bloc-entreprise.actif {

            display: block;

        }


        #bloc-entreprise .form-section-title {

            margin-top:
                0 !important;

            color:
                var(--logitix-navy) !important;

        }



        /* =========================================
           MOT DE PASSE
        ========================================== */

        .password-wrap {

            position: relative;

        }


        .password-wrap .form-control {

            padding-right:
                45px !important;

        }


        .toggle-pass {

            position: absolute;

            right: 10px;

            top: 50%;

            z-index: 3;

            width: 34px;

            height: 34px;

            display: grid;

            place-items: center;

            transform:
                translateY(-50%);

            padding: 0;

            border: none;

            border-radius: 8px;

            background:
                transparent;

            color:
                #7B8691;

            cursor:
                pointer;

        }


        .toggle-pass:hover {

            background:
                rgba(1, 32, 63, .05);

            color:
                var(--logitix-navy);

        }


        .strength-meter {

            height: 6px;

            overflow: hidden;

            margin-top: 8px;

            border-radius: 100px;

            background:
                #E8ECEF;

        }


        .strength-meter-bar {

            width: 0;

            height: 100%;

            border-radius: 100px;

            transition:
                width .25s ease,
                background-color .25s ease;

        }


        .strength-label {

            margin-top: 5px;

            color:
                var(--logitix-muted);

            font-size: .72rem;

        }


        .match-feedback {

            min-height: 20px;

            margin-top: 5px;

            font-size: .74rem;

        }


        .match-ok {

            color:
                var(--logitix-success);

        }


        .match-bad {

            color:
                var(--logitix-danger);

        }



        /* =========================================
           CONFIDENTIALITE
        ========================================== */

        .politique {

            width: 100%;

            margin-top: 2px;

            padding:
                19px;

            border:
                1px solid
                var(--logitix-border);

            border-radius:
                13px;

            background:
                #FAFBFC;

            color:
                var(--logitix-text);

        }


        .politique h5 {

            margin:
                0 0 13px;

            color:
                var(--logitix-navy);

            font-size:
                .95rem;

            font-weight:
                800;

            text-align:
                left;

            text-decoration:
                none;

        }


        .politique-consent-option {

            display:
                flex;

            align-items:
                center;

            gap:
                9px;

            min-height:
                46px;

            margin-bottom:
                7px;

            padding:
                9px 12px;

            border:
                1px solid
                var(--logitix-border);

            border-radius:
                9px;

            background:
                #FFFFFF;

            font-size:
                .83rem;

            font-weight:
                500 !important;

            cursor:
                pointer;

            transition:
                .18s ease;

        }


        .politique-consent-option:hover {

            background:
                #F6F8FA;

            border-color:
                rgba(1, 32, 63, .24);

        }


        .politique-consent-option input {

            width: 17px;

            height: 17px;

            margin: 0;

            accent-color:
                var(--logitix-navy);

        }


        .politique > p {

            margin-top:
                13px !important;

            color:
                var(--logitix-muted);

            font-size:
                .75rem;

            line-height:
                1.55;

        }


        .lien-politique {

            display:
                inline-flex;

            align-items:
                center;

            margin-top:
                3px;

            padding:
                0;

            border:
                0;

            background:
                transparent;

            color:
                var(--logitix-navy);

            font-size:
                .78rem;

            font-weight:
                700;

            cursor:
                pointer;

            text-decoration:
                none;

        }


        .lien-politique:hover {

            color:
                var(--logitix-orange);

            text-decoration:
                underline;

        }



        /* =========================================
           BOUTON CREER COMPTE
        ========================================== */

        .submit-wrap {

            max-width:
                320px;

            margin:
                22px auto 0;

        }


        #soumettre {

            width:
                100%;

            min-height:
                50px;

            border:
                none !important;

            border-radius:
                10px !important;

            background:
                var(--logitix-navy)
                !important;

            color:
                #FFFFFF !important;

            font-size:
                .9rem;

            font-weight:
                800;

            box-shadow:
                0 10px 24px
                rgba(1, 32, 63, .14)
                !important;

            transition:
                .2s ease;

        }


        #soumettre:not(:disabled):hover {

            background:
                var(--logitix-navy-light)
                !important;

            transform:
                translateY(-2px);

            box-shadow:
                0 14px 30px
                rgba(1, 32, 63, .18)
                !important;

        }


        #soumettre:disabled {

            background:
                #D5DBE1 !important;

            color:
                #7D8790 !important;

            cursor:
                not-allowed;

            box-shadow:
                none !important;

            opacity:
                1;

        }



        /* =========================================
           RETOUR CONNEXION
        ========================================== */

        .retour-connexion {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                6px;

            color:
                var(--logitix-navy);

            font-size:
                .82rem;

            font-weight:
                600;

            transition:
                .2s ease;

        }


        .retour-connexion:hover {

            color:
                var(--logitix-orange);

        }



        /* =========================================
           ALERTES
        ========================================== */

        .register-form-panel .alert {

            border:
                none;

            border-radius:
                11px;

            font-size:
                .82rem;

        }



        /* =========================================
           MODALE
        ========================================== */

        #modalPolitique .modal-content {

            overflow:
                hidden;

            border:
                0;

            border-radius:
                18px;

            box-shadow:
                0 25px 70px
                rgba(1, 32, 63, .22);

        }


        #modalPolitique .modal-header {

            padding:
                18px 22px;

            border:
                0;

            background:
                var(--logitix-navy);

        }


        #modalPolitique .modal-title {

            color:
                #FFFFFF;

            font-weight:
                800;

        }


        #modalPolitique .modal-header .btn-close {

            filter:
                invert(1);

        }


        #modalPolitique .modal-body {

            padding:
                24px;

        }


        #modalPolitique .modal-body h6 {

            margin-top:
                18px;

            color:
                var(--logitix-navy);

            font-weight:
                800;

        }


        #modalPolitique .modal-body p {

            color:
                #505C68;

            font-size:
                .87rem;

            line-height:
                1.65;

        }



        /* =========================================
           FOOTER
        ========================================== */

        .register-footer {

            padding:
                25px 0;

            background:
                var(--logitix-navy);

            color:
                rgba(255, 255, 255, .62);

        }


        .register-footer-title {

            display:
                block;

            margin-bottom:
                8px;

            color:
                #FFFFFF;

            font-size:
                .78rem;

            font-weight:
                700;

            text-transform:
                uppercase;

            letter-spacing:
                .07em;

        }


        .register-footer p {

            margin:
                0;

            color:
                rgba(255, 255, 255, .60);

            font-size:
                .78rem;

            line-height:
                1.55;

        }


        .register-footer a {

            color:
                rgba(255, 255, 255, .68);

            transition:
                .2s ease;

        }


        .register-footer a:hover {

            color:
                #FFFFFF;

        }


        .footer-socials {

            display:
                flex;

            gap:
                8px;

            margin-top:
                11px;

        }


        .footer-social {

            width:
                35px;

            height:
                35px;

            display:
                grid;

            place-items:
                center;

            border-radius:
                50%;

            background:
                rgba(255, 255, 255, .07);

            color:
                #FFFFFF !important;

        }


        .footer-social:hover {

            background:
                var(--logitix-orange);

        }



        /* =========================================
           RESPONSIVE
        ========================================== */

        @media (
            max-width: 991.98px
        ) {

            .register-side {

                min-height:
                    auto;

                padding:
                    32px;

            }


            .register-side-main {

                margin-top:
                    30px;

            }


            .side-security {

                margin-top:
                    28px;

            }


            .register-form-panel {

                padding:
                    32px;

            }

        }


        @media (
            max-width: 767.98px
        ) {

            .register-topbar {

                height:
                    auto;

                padding:
                    10px 0;

            }


            .topbar-brand-subtitle {

                display:
                    none;

            }


            .topbar-home {

                display:
                    none;

            }


            .register-page {

                padding:
                    20px 10px
                    35px;

            }


            .register-shell {

                border-radius:
                    17px;

            }


            .register-side {

                padding:
                    27px 23px;

            }


            .register-side h1 {

                font-size:
                    1.8rem;

            }


            .side-features {

                margin-top:
                    24px;

            }


            .register-form-panel {

                padding:
                    28px 20px
                    32px;

            }


            .form-section-title {

                margin-top:
                    24px;

            }


            #bloc-entreprise {

                padding:
                    15px;

            }


            .politique {

                padding:
                    16px;

            }


            .register-footer {

                text-align:
                    center;

            }


            .footer-socials {

                justify-content:
                    center;

            }

        }

    </style>

</head>


<body class="reservation-page">



    <!-- =========================================
         TOPBAR
    ========================================== -->

    <header class="register-topbar">

        <div class="container">

            <div class="register-topbar-inner">

                <a
                    href="index.html"
                    class="topbar-brand"
                >

                    <img
                        src="images/logo.png"
                        alt="LOGITIX"
                    >

                    <span class="topbar-brand-text">

                        <span class="topbar-brand-name">
                            LOGITIX
                        </span>

                        <span class="topbar-brand-subtitle">
                            Solutions logistiques
                        </span>

                    </span>

                </a>


                <div class="topbar-links">

                    <a
                        href="index.html"
                        class="topbar-home"
                    >

                        <i class="bi bi-house me-1"></i>

                        Accueil

                    </a>


                    <a
                        href="se_connecter.php"
                        class="topbar-login"
                    >

                        <i class="bi bi-box-arrow-in-right"></i>

                        Se connecter

                    </a>

                </div>

            </div>

        </div>

    </header>



    <main>


        <section class="register-page">

            <div class="container">

                <div class="register-shell">


                    <div class="row g-0 register-shell-row">


                        <!-- =========================================
                             COLONNE GAUCHE
                        ========================================== -->

                        <div class="col-lg-5">

                            <aside class="register-side h-100">


                                <div>

                                    <img
                                        src="images/logo.png"
                                        alt="LOGITIX"
                                        class="side-logo"
                                    >

                                </div>


                                <div class="register-side-main">

                                    <div class="side-badge">

                                        <i class="bi bi-shield-check"></i>

                                        Espace client sécurisé

                                    </div>


                                    <h1>

                                        Gérez votre logistique simplement.

                                    </h1>


                                    <p class="register-side-description">

                                        Créez votre espace LOGITIX pour
                                        suivre vos expéditions, gérer vos
                                        demandes de devis et centraliser
                                        vos opérations logistiques.

                                    </p>


                                    <div class="side-features">


                                        <div class="side-feature">

                                            <div class="side-feature-icon">

                                                <i class="bi bi-box-seam"></i>

                                            </div>

                                            <span>
                                                Suivi de vos expéditions
                                            </span>

                                        </div>


                                        <div class="side-feature">

                                            <div class="side-feature-icon">

                                                <i class="bi bi-file-earmark-text"></i>

                                            </div>

                                            <span>
                                                Gestion de vos demandes de devis
                                            </span>

                                        </div>


                                        <div class="side-feature">

                                            <div class="side-feature-icon">

                                                <i class="bi bi-truck"></i>

                                            </div>

                                            <span>
                                                Accès à vos services logistiques
                                            </span>

                                        </div>


                                        <div class="side-feature">

                                            <div class="side-feature-icon">

                                                <i class="bi bi-shield-lock"></i>

                                            </div>

                                            <span>
                                                Compte personnel protégé
                                            </span>

                                        </div>


                                    </div>

                                </div>


                                <div class="side-security">

                                    <strong>

                                        <i class="bi bi-lock me-1"></i>

                                        Vos informations sont protégées

                                    </strong>

                                    <p>

                                        Les données saisies servent uniquement
                                        à la gestion de votre compte et de vos
                                        opérations LOGITIX.

                                    </p>

                                </div>


                            </aside>

                        </div>



                        <!-- =========================================
                             FORMULAIRE
                        ========================================== -->

                        <div class="col-lg-7">


                            <div class="register-form-panel">


                                <div class="form-header">

                                    <div class="form-eyebrow">

                                        Espace client

                                    </div>


                                    <h2>

                                        Créer votre compte

                                    </h2>


                                    <p>

                                        Renseignez les informations ci-dessous
                                        pour ouvrir votre espace personnel LOGITIX.

                                    </p>

                                </div>



                                <form
                                    class="custom-form booking-form"
                                    action="client/traitement_inscription.php"
                                    method="post"
                                    role="form"
                                    id="formInscription"
                                    novalidate
                                >

                                    <?= csrfField() ?>


                                    <!-- =========================================
                                         ERREURS
                                    ========================================== -->

                                    <?php if (!empty($errors)): ?>

                                        <div class="alert alert-danger">

                                            <ul class="mb-0">

                                                <?php foreach ($errors as $error): ?>

                                                    <li>
                                                        <?= e($error) ?>
                                                    </li>

                                                <?php endforeach; ?>

                                            </ul>

                                        </div>

                                    <?php endif; ?>


                                    <?php displayFlash(); ?>



                                    <!-- =========================================
                                         INFORMATIONS PERSONNELLES
                                    ========================================== -->

                                    <p class="form-section-title">

                                        Informations personnelles

                                    </p>


                                    <div class="row g-3">


                                        <div class="col-md-6">

                                            <div class="field-icon">

                                                <i class="bi bi-person"></i>

                                                <input
                                                    type="text"
                                                    name="nom"
                                                    id="booking-form-name"
                                                    class="form-control"
                                                    placeholder="Nom"
                                                    required
                                                    value="<?= e($old['nom'] ?? '') ?>"
                                                >

                                            </div>

                                        </div>


                                        <div class="col-md-6">

                                            <div class="field-icon">

                                                <i class="bi bi-person"></i>

                                                <input
                                                    type="text"
                                                    name="prenom"
                                                    id="cree-form-prenom"
                                                    class="form-control"
                                                    placeholder="Prénoms"
                                                    required
                                                    value="<?= e($old['prenom'] ?? '') ?>"
                                                >

                                            </div>

                                        </div>


                                        <div class="col-md-6">

                                            <div class="field-icon">

                                                <i class="bi bi-envelope"></i>

                                                <input
                                                    type="email"
                                                    name="email"
                                                    id="cree-form-mail"
                                                    class="form-control"
                                                    placeholder="Adresse email"
                                                    required
                                                    value="<?= e($old['email'] ?? '') ?>"
                                                >

                                            </div>

                                        </div>


                                        <div class="col-md-6">

                                            <div class="field-icon">

                                                <i class="bi bi-telephone"></i>

                                                <input
                                                    type="tel"
                                                    class="form-control"
                                                    name="telephone"
                                                    id="cree-form-phone"
                                                    placeholder="Numéro de téléphone"
                                                    pattern="[0-9]{3}-[0-9]{3}-[0-9]{4}"
                                                    required
                                                    value="<?= e($old['telephone'] ?? '') ?>"
                                                >

                                            </div>

                                            <div class="field-help">

                                                Format : 07X-XXX-XXXX

                                            </div>

                                        </div>


                                        <div class="col-md-6">

                                            <div class="field-icon">

                                                <i class="bi bi-briefcase"></i>

                                                <select
                                                    class="form-control"
                                                    name="status"
                                                    id="cree-form-status"
                                                    required
                                                >

                                                    <option
                                                        value=""
                                                        selected
                                                        disabled
                                                    >
                                                        Qu'êtes-vous ?
                                                    </option>

                                                    <option
                                                        value="particulier"
                                                        <?= (($old['status'] ?? '') === 'particulier') ? 'selected' : '' ?>
                                                    >
                                                        Particulier
                                                    </option>

                                                    <option
                                                        value="entreprise"
                                                        <?= (($old['status'] ?? '') === 'entreprise') ? 'selected' : '' ?>
                                                    >
                                                        Entreprise
                                                    </option>

                                                    <option
                                                        value="cooperative"
                                                        <?= (($old['status'] ?? '') === 'cooperative') ? 'selected' : '' ?>
                                                    >
                                                        Coopérative / Association
                                                    </option>

                                                </select>

                                            </div>

                                        </div>


                                    </div>



                                    <!-- =========================================
                                         ENTREPRISE
                                    ========================================== -->

                                    <div
                                        id="bloc-entreprise"
                                        class="<?= (($old['status'] ?? '') === 'entreprise' || ($old['status'] ?? '') === 'cooperative') ? 'actif' : '' ?>"
                                    >


                                        <p class="form-section-title">

                                            Informations professionnelles

                                        </p>


                                        <div class="row g-3">


                                            <div class="col-md-6">

                                                <div class="field-icon">

                                                    <i class="bi bi-building"></i>

                                                    <input
                                                        type="text"
                                                        name="raison_sociale"
                                                        id="cree-form-raison-sociale"
                                                        class="form-control"
                                                        placeholder="Entreprise / structure"
                                                        value="<?= e($old['raison_sociale'] ?? '') ?>"
                                                    >

                                                </div>

                                            </div>


                                            <div class="col-md-6">

                                                <div class="field-icon">

                                                    <i class="bi bi-file-earmark-text"></i>

                                                    <input
                                                        type="text"
                                                        name="rccm"
                                                        id="cree-form-rccm"
                                                        class="form-control"
                                                        placeholder="N° RCCM / Immatriculation"
                                                        value="<?= e($old['rccm'] ?? '') ?>"
                                                    >

                                                </div>


                                                <div class="field-help">

                                                    Laisser vide si non applicable

                                                </div>

                                            </div>


                                        </div>


                                    </div>



                                    <!-- =========================================
                                         SECURITE
                                    ========================================== -->

                                    <p class="form-section-title">

                                        Sécurité du compte

                                    </p>


                                    <div class="row g-3">


                                        <div class="col-md-6">

                                            <div class="password-wrap">

                                                <input
                                                    type="password"
                                                    class="form-control"
                                                    name="mot_de_passe"
                                                    id="cree-form-passwd"
                                                    placeholder="Mot de passe"
                                                    required
                                                >


                                                <button
                                                    type="button"
                                                    class="toggle-pass"
                                                    data-target="cree-form-passwd"
                                                    aria-label="Afficher le mot de passe"
                                                >

                                                    <i class="bi bi-eye"></i>

                                                </button>

                                            </div>


                                            <div class="strength-meter">

                                                <div
                                                    class="strength-meter-bar"
                                                    id="strengthBar"
                                                ></div>

                                            </div>


                                            <div
                                                class="strength-label"
                                                id="strengthLabel"
                                            >

                                                Force du mot de passe

                                            </div>

                                        </div>



                                        <div class="col-md-6">

                                            <div class="password-wrap">

                                                <input
                                                    type="password"
                                                    class="form-control"
                                                    name="confirmation"
                                                    id="cree-form-confpasswd"
                                                    placeholder="Confirmer le mot de passe"
                                                    required
                                                >


                                                <button
                                                    type="button"
                                                    class="toggle-pass"
                                                    data-target="cree-form-confpasswd"
                                                    aria-label="Afficher le mot de passe"
                                                >

                                                    <i class="bi bi-eye"></i>

                                                </button>

                                            </div>


                                            <div
                                                class="match-feedback"
                                                id="matchFeedback"
                                            >
                                                &nbsp;
                                            </div>

                                        </div>


                                    </div>



                                    <!-- =========================================
                                         CONFIDENTIALITE
                                    ========================================== -->

                                    <p class="form-section-title">

                                        Confidentialité

                                    </p>


                                    <div class="politique col-12">


                                        <h5>

                                            Politique de confidentialité

                                        </h5>


                                        <label
                                            class="politique-consent-option"
                                            for="accepte"
                                        >

                                            <input
                                                type="radio"
                                                name="confid"
                                                id="accepte"
                                                value="accepte"
                                                <?= (($old['confid'] ?? '') === 'accepte') ? 'checked' : '' ?>
                                            >

                                            J'accepte la politique de confidentialité

                                        </label>


                                        <label
                                            class="politique-consent-option"
                                            for="refuse"
                                        >

                                            <input
                                                type="radio"
                                                name="confid"
                                                id="refuse"
                                                value="refuse"
                                                <?= (($old['confid'] ?? '') === 'refuse') ? 'checked' : '' ?>
                                            >

                                            Je refuse

                                        </label>


                                        <p class="mb-0">

                                            En cliquant sur « J'accepte »,
                                            vous acceptez de vous soumettre
                                            à notre politique de confidentialité.

                                        </p>


                                        <button
                                            type="button"
                                            class="lien-politique"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalPolitique"
                                        >

                                            <i class="bi bi-file-text me-1"></i>

                                            Lire la politique complète

                                        </button>


                                    </div>



                                    <!-- =========================================
                                         SUBMIT
                                    ========================================== -->

                                    <div class="submit-wrap">

                                        <button
                                            type="submit"
                                            class="form-control"
                                            id="soumettre"
                                            disabled
                                        >

                                            Créer mon compte

                                        </button>

                                    </div>



                                    <div class="text-center mt-3">

                                        <a
                                            href="se_connecter.php"
                                            class="retour-connexion"
                                        >

                                            <i class="bi bi-arrow-left"></i>

                                            Déjà inscrit ? Connectez-vous

                                        </a>

                                    </div>



                                    <!-- Piège à robots -->

                                    <input
                                        type="text"
                                        name="site_web"
                                        id="site_web"
                                        style="position:absolute; left:-9999px;"
                                        tabindex="-1"
                                        autocomplete="off"
                                    >


                                </form>


                            </div>

                        </div>


                    </div>

                </div>

            </div>

        </section>


    </main>



    <!-- =========================================
         FOOTER
    ========================================== -->

    <footer class="register-footer">

        <div class="container">

            <div class="row align-items-start g-4">


                <div class="col-lg-5 col-md-6">

                    <span class="register-footer-title">

                        LOGITIX

                    </span>


                    <p>

                        <i class="bi bi-geo-alt me-1"></i>

                        Terminal à Conteneurs de Vridi,
                        Port Autonome d'Abidjan,
                        Côte d'Ivoire

                    </p>


                    <div class="footer-socials">

                        <a
                            href="https://www.facebook.com/logitix"
                            class="footer-social"
                            aria-label="Facebook"
                        >

                            <i class="bi bi-facebook"></i>

                        </a>


                        <a
                            href="https://wa.me/2250503231625?text=Bonjour j'aimerais avoir plus d'information sur vos services de livraison"
                            class="footer-social"
                            aria-label="WhatsApp"
                        >

                            <i class="bi bi-whatsapp"></i>

                        </a>

                    </div>

                </div>



                <div class="col-lg-4 col-md-6">

                    <span class="register-footer-title">

                        Contact

                    </span>


                    <p>

                        <i class="bi bi-telephone me-1"></i>

                        <a href="tel:+2250503231625">

                            (+225) 05 03 23 16 25

                        </a>

                    </p>


                    <p class="mt-1">

                        <i class="bi bi-telephone me-1"></i>

                        <a href="tel:+2250151306051">

                            (+225) 01 51 30 60 51

                        </a>

                    </p>


                    <p class="mt-1">

                        <i class="bi bi-envelope me-1"></i>

                        <a href="mailto:oichristkouame920@gmail.com">

                            oichristkouame920@gmail.com

                        </a>

                    </p>

                </div>



                <div class="col-lg-3">

                    <span class="register-footer-title">

                        Accès rapide

                    </span>


                    <p>

                        <a href="index.html">

                            Retour à l'accueil

                        </a>

                    </p>


                    <p class="mt-1">

                        <a href="se_connecter.php">

                            Connexion client

                        </a>

                    </p>

                </div>


                <div class="col-12">

                    <hr
                        style="
                            border-color:
                            rgba(255,255,255,.12);
                        "
                    >


                    <p class="text-center">

                        Copyright © kouame — Design 2025

                    </p>

                </div>


            </div>

        </div>

    </footer>



    <!-- =========================================
         MODALE POLITIQUE
    ========================================== -->

    <div
        class="modal fade"
        id="modalPolitique"
        tabindex="-1"
        aria-hidden="true"
    >

        <div
            class="modal-dialog
                   modal-dialog-scrollable
                   modal-lg"
        >

            <div class="modal-content">


                <div class="modal-header">

                    <h5 class="modal-title">

                        Politique de confidentialité — LOGITIX

                    </h5>


                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Fermer"
                    ></button>

                </div>


                <div class="modal-body">


                    <p>

                        LOGITIX s'engage à protéger les données
                        personnelles de ses clients et utilisateurs,
                        conformément à la réglementation en vigueur
                        sur la protection des données.

                    </p>


                    <h6>
                        1. Données collectées
                    </h6>

                    <p>

                        Nom, prénom, adresse email, numéro de téléphone,
                        et le cas échéant les informations relatives à
                        votre entreprise (raison sociale, numéro
                        d'immatriculation), collectées lors de la
                        création de votre compte.

                    </p>


                    <h6>
                        2. Utilisation des données
                    </h6>

                    <p>

                        Vos données sont utilisées pour créer et gérer
                        votre espace client, traiter vos demandes de
                        devis, assurer le suivi de vos expéditions,
                        et vous transmettre des notifications relatives
                        à votre compte.

                    </p>


                    <h6>
                        3. Conservation
                    </h6>

                    <p>

                        Vos données sont conservées pendant toute la
                        durée de votre relation avec LOGITIX, puis
                        archivées ou supprimées conformément aux
                        obligations légales applicables.

                    </p>


                    <h6>
                        4. Partage des données
                    </h6>

                    <p>

                        Vos données ne sont ni vendues, ni cédées
                        à des tiers. Elles peuvent être partagées
                        avec nos partenaires opérationnels
                        (transporteurs, chauffeurs affectés)
                        uniquement dans le cadre de l'exécution
                        de votre expédition.

                    </p>


                    <h6>
                        5. Vos droits
                    </h6>

                    <p>

                        Vous disposez d'un droit d'accès,
                        de rectification et de suppression
                        de vos données.

                        Toute demande peut être adressée à

                        <strong>
                            oichristkouame920@gmail.com
                        </strong>.

                    </p>


                    <h6>
                        6. Sécurité
                    </h6>

                    <p>

                        Votre mot de passe est stocké de façon
                        chiffrée. L'accès à votre espace client
                        est protégé par une authentification
                        personnelle que nous vous recommandons
                        de garder confidentielle.

                    </p>


                </div>

            </div>

        </div>

    </div>



    <!-- =========================================
         JAVASCRIPT FILES
    ========================================== -->

    <script src="js/jquery.min.js"></script>

    <script src="js/bootstrap.min.js"></script>

    <script src="js/jquery.sticky.js"></script>

    <script src="js/vegas.min.js"></script>

    <script src="js/custom.js"></script>



    <script>

        /*
         * Active le bouton uniquement lorsque
         * la politique est acceptée.
         */

        let accepte =
            document.getElementById("accepte");

        let refuse =
            document.getElementById("refuse");

        let boutonCreer =
            document.getElementById("soumettre");


        function verifierAcceptation() {

            boutonCreer.disabled =
                !accepte.checked;

        }


        accepte.addEventListener(
            "change",
            verifierAcceptation
        );


        refuse.addEventListener(
            "change",
            verifierAcceptation
        );


        /*
         * Important en cas de retour du formulaire
         * avec les anciennes valeurs enregistrées.
         */

        verifierAcceptation();

    </script>



    <script>

        /*
         * Affiche les informations professionnelles
         * pour entreprise ou coopérative.
         */

        const statutSelect =
            document.getElementById(
                "cree-form-status"
            );


        const blocEntreprise =
            document.getElementById(
                "bloc-entreprise"
            );


        const champRaisonSociale =
            document.getElementById(
                "cree-form-raison-sociale"
            );


        function verifierStatutProfessionnel() {

            const estProfessionnel =
                (
                    statutSelect.value ===
                    "entreprise"
                    ||
                    statutSelect.value ===
                    "cooperative"
                );


            blocEntreprise.classList.toggle(
                "actif",
                estProfessionnel
            );


            champRaisonSociale.required =
                estProfessionnel;

        }


        statutSelect.addEventListener(
            "change",
            verifierStatutProfessionnel
        );


        verifierStatutProfessionnel();

    </script>



    <script>

        /*
         * Afficher / masquer le mot de passe.
         */

        document
            .querySelectorAll(".toggle-pass")
            .forEach(function (btn) {

                btn.addEventListener(
                    "click",
                    function () {

                        const champ =
                            document.getElementById(
                                this.getAttribute(
                                    "data-target"
                                )
                            );


                        const icone =
                            this.querySelector("i");


                        const visible =
                            champ.type === "text";


                        champ.type =
                            visible
                                ? "password"
                                : "text";


                        icone.classList.toggle(
                            "bi-eye",
                            visible
                        );


                        icone.classList.toggle(
                            "bi-eye-slash",
                            !visible
                        );

                    }
                );

            });

    </script>



    <script>

        /*
         * Indicateur de force du mot de passe
         * + vérification de correspondance.
         */

        const champMdp =
            document.getElementById(
                "cree-form-passwd"
            );


        const champConfMdp =
            document.getElementById(
                "cree-form-confpasswd"
            );


        const barreForce =
            document.getElementById(
                "strengthBar"
            );


        const labelForce =
            document.getElementById(
                "strengthLabel"
            );


        const retourCorrespondance =
            document.getElementById(
                "matchFeedback"
            );


        function evaluerForce(mdp) {

            let score = 0;


            if (mdp.length >= 8)
                score++;


            if (mdp.length >= 12)
                score++;


            if (/[A-Z]/.test(mdp))
                score++;


            if (/[0-9]/.test(mdp))
                score++;


            if (/[^A-Za-z0-9]/.test(mdp))
                score++;


            return score;

        }


        const etapes = [

            {
                largeur: "0%",
                couleur: "transparent",
                texte: "Force du mot de passe"
            },

            {
                largeur: "20%",
                couleur: "#dc3545",
                texte: "Très faible"
            },

            {
                largeur: "40%",
                couleur: "#dc3545",
                texte: "Faible"
            },

            {
                largeur: "60%",
                couleur: "#ffc107",
                texte: "Moyen"
            },

            {
                largeur: "80%",
                couleur: "#8bd17c",
                texte: "Bon"
            },

            {
                largeur: "100%",
                couleur: "#28a745",
                texte: "Excellent"
            }

        ];


        champMdp.addEventListener(
            "input",
            function () {

                let score =
                    evaluerForce(
                        this.value
                    );


                if (
                    this.value !== ""
                    &&
                    score === 0
                ) {

                    score = 1;

                }


                score =
                    Math.min(
                        score,
                        5
                    );


                const etape =
                    etapes[score];


                barreForce.style.width =
                    etape.largeur;


                barreForce.style.backgroundColor =
                    etape.couleur;


                labelForce.textContent =
                    etape.texte;


                verifierCorrespondance();

            }
        );


        function verifierCorrespondance() {

            if (
                champConfMdp.value === ""
            ) {

                retourCorrespondance.innerHTML =
                    "&nbsp;";

                return;

            }


            if (
                champMdp.value ===
                champConfMdp.value
            ) {

                retourCorrespondance.textContent =
                    "Les mots de passe correspondent ✓";


                retourCorrespondance.className =
                    "match-feedback match-ok";

            }

            else {

                retourCorrespondance.textContent =
                    "Les mots de passe ne correspondent pas";


                retourCorrespondance.className =
                    "match-feedback match-bad";

            }

        }


        champConfMdp.addEventListener(
            "input",
            verifierCorrespondance
        );

    </script>


</body>

</html>