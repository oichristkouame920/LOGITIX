<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

$changePasswordIntent = (string) ($_GET['action'] ?? '') === 'change_password';

if (isLoggedIn()) {
    if ($changePasswordIntent) {
        redirect(isAdmin() ? 'admin/changer_mot_de_passe.php' : 'client/changer_mot_de_passe.php');
    }

    if (isAdmin()) {
        redirect('admin/dashboard.php');
    } else {
        redirect('client/dashboard.php');
    }
}
?>

<!doctype html>

<html lang="fr">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <meta
        name="description"
        content="Connexion à l'espace client LOGITIX"
    >

    <meta
        name="author"
        content="LOGITIX"
    >

    <title>Se connecter - LOGITIX</title>


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

            --logitix-bg: #F3F5F7;

            --logitix-white: #FFFFFF;

            --logitix-text: #18222D;

            --logitix-muted: #727D88;

            --logitix-border: #DEE4EA;

            --logitix-soft: #F8FAFC;

        }


        * {

            box-sizing: border-box;

        }


        html {

            scroll-behavior: smooth;

        }


        body {

            margin: 0;

            min-height: 100vh;

            background:

                radial-gradient(
                    circle at 12% 10%,
                    rgba(210, 105, 30, .05),
                    transparent 28%
                ),

                linear-gradient(
                    180deg,
                    #F7F8FA 0%,
                    #EFF2F5 100%
                );

            color:
                var(--logitix-text);

            font-family:
                'Jost',
                sans-serif;

        }


        a {

            text-decoration: none;

        }



        /* =====================================
           BARRE SUPERIEURE
        ====================================== */

        .login-topbar {

            min-height: 72px;

            display: flex;

            align-items: center;

            background: #FFFFFF;

            border-bottom:
                1px solid
                rgba(1, 32, 63, .08);

            box-shadow:
                0 4px 20px
                rgba(1, 32, 63, .04);

        }


        .login-topbar-inner {

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 20px;

        }


        .brand-link {

            display: flex;

            align-items: center;

            gap: 12px;

        }


        .brand-link img {

            width: 48px;

            height: 48px;

            object-fit: contain;

        }


        .brand-copy {

            line-height: 1;

        }


        .brand-name {

            display: block;

            color:
                var(--logitix-navy);

            font-size: 1.2rem;

            font-weight: 800;

            letter-spacing: .04em;

        }


        .brand-subtitle {

            display: block;

            margin-top: 5px;

            color:
                var(--logitix-muted);

            font-size: .7rem;

            text-transform: uppercase;

            letter-spacing: .08em;

        }


        .topbar-nav {

            display: flex;

            align-items: center;

            gap: 3px;

        }


        .topbar-nav > a {

            padding:
                9px 11px;

            border-radius:
                9px;

            color:
                var(--logitix-navy);

            font-size:
                .81rem;

            font-weight:
                600;

            transition:
                .2s ease;

        }


        .topbar-nav > a:hover {

            background:
                rgba(1, 32, 63, .05);

            color:
                var(--logitix-navy);

        }


        .topbar-create {

            margin-left: 7px;

            display: inline-flex !important;

            align-items: center;

            gap: 7px;

            padding:
                10px 15px !important;

            background:
                var(--logitix-navy);

            color:
                #FFFFFF !important;

        }


        .topbar-create:hover {

            background:
                var(--logitix-navy-light)
                !important;

            color:
                #FFFFFF !important;

        }



        /* =====================================
           PAGE
        ====================================== */

        .login-page {

            min-height:
                calc(100vh - 72px);

            display: flex;

            align-items: center;

            padding:
                45px 0 55px;

        }


        .login-shell {

            overflow: hidden;

            background:
                #FFFFFF;

            border:
                1px solid
                rgba(1, 32, 63, .08);

            border-radius:
                24px;

            box-shadow:
                0 25px 70px
                rgba(1, 32, 63, .12);

        }


        .login-shell-row {

            min-height:
                610px;

        }



        /* =====================================
           PARTIE GAUCHE
        ====================================== */

        .login-side {

            min-height:
                610px;

            position: relative;

            overflow: hidden;

            display: flex;

            flex-direction: column;

            justify-content: space-between;

            padding:
                42px;

            color:
                #FFFFFF;

            background:

                radial-gradient(
                    circle at 18% 15%,
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


        .login-side::before {

            content: "";

            position: absolute;

            width: 280px;

            height: 280px;

            right: -130px;

            bottom: -110px;

            border-radius:
                50%;

            border:
                1px solid
                rgba(255, 255, 255, .08);

            box-shadow:

                0 0 0 35px
                rgba(255, 255, 255, .025),

                0 0 0 70px
                rgba(255, 255, 255, .015);

        }


        .login-side > * {

            position: relative;

            z-index: 2;

        }


        .side-logo {

            width: 165px;

            height: auto;

            object-fit: contain;

        }


        .side-main {

            margin-top:
                30px;

        }


        .side-badge {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                7px;

            margin-bottom:
                18px;

            padding:
                7px 12px;

            border:
                1px solid
                rgba(255, 255, 255, .14);

            border-radius:
                50px;

            background:
                rgba(255, 255, 255, .07);

            color:
                rgba(255, 255, 255, .78);

            font-size:
                .73rem;

            font-weight:
                600;

        }


        .login-side h1 {

            max-width:
                430px;

            margin:
                0 0 16px;

            color:
                #FFFFFF;

            font-size:
                clamp(2rem, 4vw, 2.8rem);

            font-weight:
                800;

            line-height:
                1.12;

        }


        .side-description {

            max-width:
                430px;

            margin:
                0;

            color:
                rgba(255, 255, 255, .65);

            font-size:
                .93rem;

            line-height:
                1.7;

        }


        .side-features {

            display: flex;

            flex-direction: column;

            gap: 13px;

            margin-top:
                30px;

        }


        .side-feature {

            display: flex;

            align-items: center;

            gap: 11px;

            color:
                rgba(255, 255, 255, .82);

            font-size:
                .83rem;

        }


        .side-feature-icon {

            width: 35px;

            height: 35px;

            flex:
                0 0 35px;

            display: grid;

            place-items: center;

            border-radius:
                10px;

            background:
                rgba(210, 105, 30, .17);

            color:
                #F2AE7E;

        }


        .side-note {

            margin-top:
                34px;

            padding:
                15px;

            border:
                1px solid
                rgba(255, 255, 255, .09);

            border-radius:
                13px;

            background:
                rgba(255, 255, 255, .045);

        }


        .side-note strong {

            display:
                block;

            margin-bottom:
                4px;

            color:
                #FFFFFF;

            font-size:
                .8rem;

        }


        .side-note p {

            margin:
                0;

            color:
                rgba(255, 255, 255, .50);

            font-size:
                .71rem;

            line-height:
                1.5;

        }



        /* =====================================
           FORMULAIRE
        ====================================== */

        .login-form-panel {

            height: 100%;

            display: flex;

            flex-direction: column;

            justify-content: center;

            padding:
                46px 48px;

            background:
                #FFFFFF;

        }


        .form-eyebrow {

            margin-bottom:
                8px;

            color:
                var(--logitix-orange);

            font-size:
                .69rem;

            font-weight:
                800;

            text-transform:
                uppercase;

            letter-spacing:
                .12em;

        }


        .login-form-panel h2 {

            margin:
                0 0 8px;

            color:
                var(--logitix-navy);

            font-size:
                clamp(1.65rem, 3vw, 2.05rem);

            font-weight:
                800;

        }


        .form-subtitle {

            margin:
                0 0 28px;

            color:
                var(--logitix-muted);

            font-size:
                .88rem;

            line-height:
                1.6;

        }


        .login-form-panel .alert {

            border:
                none;

            border-radius:
                11px;

            font-size:
                .82rem;

        }


        .field-label {

            display:
                block;

            margin-bottom:
                7px;

            color:
                var(--logitix-navy);

            font-size:
                .78rem;

            font-weight:
                700;

        }


        .field-wrap {

            position:
                relative;

        }


        .field-wrap > i {

            position:
                absolute;

            left:
                14px;

            top:
                50%;

            z-index:
                2;

            transform:
                translateY(-50%);

            color:
                #7D8995;

            pointer-events:
                none;

        }


        .login-control {

            width:
                100%;

            min-height:
                50px;

            padding:
                11px 44px 11px 41px;

            border:
                1px solid
                var(--logitix-border);

            border-radius:
                10px;

            background:
                #FFFFFF;

            color:
                var(--logitix-text);

            font-size:
                .9rem;

            outline:
                none;

            transition:
                .2s ease;

        }


        .login-control::placeholder {

            color:
                #99A2AB;

        }


        .login-control:focus {

            border-color:
                rgba(1, 32, 63, .48);

            box-shadow:
                0 0 0 3px
                rgba(1, 32, 63, .055);

        }


        .password-toggle {

            position:
                absolute;

            right:
                10px;

            top:
                50%;

            z-index:
                3;

            width:
                34px;

            height:
                34px;

            display:
                grid;

            place-items:
                center;

            transform:
                translateY(-50%);

            padding:
                0;

            border:
                0;

            border-radius:
                8px;

            background:
                transparent;

            color:
                #7B8691;

            cursor:
                pointer;

        }


        .password-toggle:hover {

            background:
                rgba(1, 32, 63, .05);

            color:
                var(--logitix-navy);

        }



        /* =====================================
           BOUTON CONNEXION
        ====================================== */

        .btn-connect {

            width:
                100%;

            min-height:
                50px;

            display:
                inline-flex;

            align-items:
                center;

            justify-content:
                center;

            gap:
                8px;

            margin-top:
                5px;

            border:
                none;

            border-radius:
                10px;

            background:
                var(--logitix-navy);

            color:
                #FFFFFF;

            font-size:
                .9rem;

            font-weight:
                800;

            box-shadow:
                0 10px 24px
                rgba(1, 32, 63, .14);

            transition:
                .2s ease;

        }


        .btn-connect:hover {

            background:
                var(--logitix-navy-light);

            color:
                #FFFFFF;

            transform:
                translateY(-2px);

            box-shadow:
                0 14px 30px
                rgba(1, 32, 63, .18);

        }



        /* =====================================
           MOT DE PASSE
        ====================================== */

        .secondary-action {

            margin-top:
                17px;

            text-align:
                center;

        }


        .secondary-action a {

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
                700;

            transition:
                .2s ease;

        }


        .secondary-action a:hover {

            color:
                var(--logitix-orange);

        }



        /* =====================================
           SEPARATEUR
        ====================================== */

        .separator {

            display:
                flex;

            align-items:
                center;

            gap:
                12px;

            margin:
                28px 0 22px;

            color:
                #A0A7AE;

            font-size:
                .7rem;

            text-transform:
                uppercase;

            letter-spacing:
                .08em;

        }


        .separator::before,
        .separator::after {

            content:
                "";

            flex:
                1;

            height:
                1px;

            background:
                #E5E9ED;

        }



        /* =====================================
           CREATION COMPTE
        ====================================== */

        .create-account-box {

            padding:
                18px;

            text-align:
                center;

            border:
                1px solid
                var(--logitix-border);

            border-radius:
                13px;

            background:
                var(--logitix-soft);

        }


        .create-account-box h3 {

            margin:
                0 0 5px;

            color:
                var(--logitix-navy);

            font-size:
                .93rem;

            font-weight:
                800;

        }


        .create-account-box p {

            margin:
                0 0 13px;

            color:
                var(--logitix-muted);

            font-size:
                .76rem;

        }


        .btn-create {

            min-height:
                44px;

            display:
                inline-flex;

            align-items:
                center;

            justify-content:
                center;

            gap:
                7px;

            padding:
                9px 17px;

            border:
                1px solid
                rgba(1, 32, 63, .16);

            border-radius:
                9px;

            background:
                #FFFFFF;

            color:
                var(--logitix-navy);

            font-size:
                .82rem;

            font-weight:
                800;

            transition:
                .2s ease;

        }


        .btn-create:hover {

            background:
                var(--logitix-navy);

            border-color:
                var(--logitix-navy);

            color:
                #FFFFFF;

        }



        /* =====================================
           FOOTER
        ====================================== */

        .login-footer {

            padding:
                25px 0;

            background:
                var(--logitix-navy);

            color:
                rgba(255, 255, 255, .62);

        }


        .footer-title {

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


        .login-footer p {

            margin:
                0;

            color:
                rgba(255, 255, 255, .60);

            font-size:
                .78rem;

            line-height:
                1.55;

        }


        .login-footer a {

            color:
                rgba(255, 255, 255, .68);

            transition:
                .2s ease;

        }


        .login-footer a:hover {

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



        /* =====================================
           RESPONSIVE
        ====================================== */

        @media (
            max-width: 991.98px
        ) {

            .topbar-nav a:not(.topbar-create) {

                display:
                    none;

            }


            .login-page {

                padding-top:
                    24px;

            }


            .login-side {

                min-height:
                    auto;

                padding:
                    32px;

            }


            .login-form-panel {

                padding:
                    34px;

            }

        }


        @media (
            max-width: 767.98px
        ) {

            .login-topbar {

                min-height:
                    64px;

            }


            .brand-subtitle {

                display:
                    none;

            }


            .brand-link img {

                width:
                    42px;

                height:
                    42px;

            }


            .login-page {

                padding:
                    20px 10px 35px;

            }


            .login-shell {

                border-radius:
                    17px;

            }


            .login-side {

                padding:
                    27px 23px;

            }


            .login-side h1 {

                font-size:
                    1.8rem;

            }


            .login-form-panel {

                padding:
                    28px 20px 32px;

            }


            .login-footer {

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


<body>


    <!-- =====================================
         TOPBAR
    ====================================== -->

    <header class="login-topbar">

        <div class="container">

            <div class="login-topbar-inner">


                <a
                    href="index.html"
                    class="brand-link"
                >

                    <img
                        src="images/logo.png"
                        alt="LOGITIX"
                    >


                    <span class="brand-copy">

                        <span class="brand-name">

                            LOGITIX

                        </span>


                        <span class="brand-subtitle">

                            Solutions logistiques

                        </span>

                    </span>

                </a>



                <nav
                    class="topbar-nav"
                    aria-label="Navigation principale"
                >

                    <a href="index.html">

                        Accueil

                    </a>


                    <a href="index.html#section_2">

                        À propos

                    </a>


                    <a href="index.html#section_3">

                        Flotte

                    </a>


                    <a href="index.html#section_4">

                        Services

                    </a>


                    <a href="index.html#section_6">

                        Entrepôts

                    </a>


                    <a
                        href="crer_compte.php"
                        class="topbar-create"
                    >

                        <i class="bi bi-person-plus"></i>

                        Créer un compte

                    </a>

                </nav>


            </div>

        </div>

    </header>



    <main>


        <section class="login-page">


            <div class="container">


                <div class="login-shell">


                    <div class="row g-0 login-shell-row">


                        <!-- ===============================
                             PARTIE GAUCHE
                        ================================ -->

                        <div class="col-lg-5">


                            <aside class="login-side">


                                <div>

                                    <img
                                        src="images/logo.png"
                                        alt="LOGITIX"
                                        class="side-logo"
                                    >

                                </div>



                                <div class="side-main">


                                    <div class="side-badge">

                                        <i class="bi bi-shield-check"></i>

                                        Espace client sécurisé

                                    </div>



                                    <h1>

                                        Retrouvez vos opérations LOGITIX.

                                    </h1>



                                    <p class="side-description">

                                        Connectez-vous pour suivre vos
                                        expéditions, consulter vos devis et
                                        gérer votre espace client depuis une
                                        interface unique et sécurisée.

                                    </p>



                                    <div class="side-features">


                                        <div class="side-feature">

                                            <div class="side-feature-icon">

                                                <i class="bi bi-box-seam"></i>

                                            </div>

                                            <span>

                                                Suivi centralisé des expéditions

                                            </span>

                                        </div>



                                        <div class="side-feature">

                                            <div class="side-feature-icon">

                                                <i class="bi bi-file-earmark-text"></i>

                                            </div>

                                            <span>

                                                Consultation de vos devis

                                            </span>

                                        </div>



                                        <div class="side-feature">

                                            <div class="side-feature-icon">

                                                <i class="bi bi-person-check"></i>

                                            </div>

                                            <span>

                                                Accès à votre espace personnel

                                            </span>

                                        </div>



                                        <div class="side-feature">

                                            <div class="side-feature-icon">

                                                <i class="bi bi-shield-lock"></i>

                                            </div>

                                            <span>

                                                Connexion protégée

                                            </span>

                                        </div>


                                    </div>


                                </div>



                                <div class="side-note">


                                    <strong>

                                        <i class="bi bi-lock me-1"></i>

                                        Connexion sécurisée

                                    </strong>


                                    <p>

                                        Ne communiquez jamais votre mot
                                        de passe à un tiers.

                                    </p>


                                </div>


                            </aside>


                        </div>



                        <!-- ===============================
                             CONNEXION
                        ================================ -->

                        <div class="col-lg-7">


                            <section class="login-form-panel">


                                <div class="form-eyebrow">

                                    <?= $changePasswordIntent
                                        ? 'Sécurité du compte'
                                        : 'Espace client'
                                    ?>

                                </div>



                                <h2>

                                    <?= $changePasswordIntent
                                        ? 'Modifier mon mot de passe'
                                        : 'Se connecter'
                                    ?>

                                </h2>



                                <p class="form-subtitle">

                                    <?= $changePasswordIntent
                                        ? 'Identifiez-vous avant de choisir un nouveau mot de passe.'
                                        : 'Accédez à votre espace client LOGITIX.'
                                    ?>

                                </p>



                                <?php displayFlash(); ?>



                                <form
                                    action="client/traitement_connexion.php"
                                    method="post"
                                >


                                    <?= csrfField() ?>


                                    <?php if ($changePasswordIntent): ?>

                                        <input
                                            type="hidden"
                                            name="intent"
                                            value="change_password"
                                        >

                                    <?php endif; ?>



                                    <!-- EMAIL -->

                                    <div class="mb-3">


                                        <label
                                            for="login-email"
                                            class="field-label"
                                        >

                                            Adresse email

                                        </label>


                                        <div class="field-wrap">


                                            <i class="bi bi-envelope"></i>


                                            <input
                                                type="email"
                                                name="email"
                                                id="login-email"
                                                class="login-control"
                                                placeholder="votre@email.com"
                                                autocomplete="email"
                                                required
                                            >


                                        </div>


                                    </div>



                                    <!-- MOT DE PASSE -->

                                    <div class="mb-3">


                                        <label
                                            for="login-password"
                                            class="field-label"
                                        >

                                            Mot de passe

                                        </label>


                                        <div class="field-wrap">


                                            <i class="bi bi-lock"></i>


                                            <input
                                                type="password"
                                                name="password"
                                                id="login-password"
                                                class="login-control"
                                                placeholder="Votre mot de passe"
                                                autocomplete="current-password"
                                                required
                                            >



                                            <button
                                                type="button"
                                                class="password-toggle"
                                                id="passwordToggle"
                                                aria-label="Afficher le mot de passe"
                                            >

                                                <i class="bi bi-eye"></i>

                                            </button>


                                        </div>


                                    </div>



                                    <!-- BOUTON -->

                                    <button
                                        type="submit"
                                        class="btn-connect"
                                    >


                                        <i
                                            class="bi <?= $changePasswordIntent
                                                ? 'bi-arrow-right-circle'
                                                : 'bi-box-arrow-in-right'
                                            ?>"
                                        ></i>


                                        <?= $changePasswordIntent
                                            ? 'Continuer'
                                            : 'Se connecter'
                                        ?>


                                    </button>


                                </form>



                                <!-- MODIFIER MOT DE PASSE -->


                                <div class="secondary-action">


                                    <?php if ($changePasswordIntent): ?>


                                        <a href="se_connecter.php">

                                            <i class="bi bi-arrow-left"></i>

                                            Retour à la connexion

                                        </a>


                                    <?php else: ?>


                                        <a href="se_connecter.php?action=change_password">

                                            <i class="bi bi-key"></i>

                                            Modifier mon mot de passe

                                        </a>


                                    <?php endif; ?>


                                </div>



                                <div class="separator">

                                    ou

                                </div>



                                <!-- CREATION COMPTE -->


                                <div class="create-account-box">


                                    <h3>

                                        Vous n'avez pas encore de compte ?

                                    </h3>


                                    <p>

                                        Créez votre espace client pour
                                        accéder aux services LOGITIX.

                                    </p>


                                    <a
                                        href="crer_compte.php"
                                        class="btn-create"
                                    >

                                        <i class="bi bi-person-plus"></i>

                                        Créer un compte

                                    </a>


                                </div>


                            </section>


                        </div>


                    </div>


                </div>


            </div>


        </section>


    </main>



    <!-- =====================================
         FOOTER
    ====================================== -->

    <footer class="login-footer">


        <div class="container">


            <div class="row align-items-start g-4">


                <div class="col-lg-5 col-md-6">


                    <span class="footer-title">

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
                            href="https://wa.me/2250503231625"
                            class="footer-social"
                            aria-label="WhatsApp"
                        >

                            <i class="bi bi-whatsapp"></i>

                        </a>


                    </div>


                </div>



                <div class="col-lg-4 col-md-6">


                    <span class="footer-title">

                        Contact

                    </span>


                    <p>

                        <i class="bi bi-telephone me-1"></i>


                        <a href="tel:+2250503231625">

                            (+225) 05 03 23 16 25

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


                    <span class="footer-title">

                        Accès rapide

                    </span>


                    <p>

                        <a href="index.html">

                            Retour à l'accueil

                        </a>

                    </p>


                    <p class="mt-1">

                        <a href="crer_compte.php">

                            Créer un compte

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



    <!-- JAVASCRIPT FILES -->

    <script src="js/jquery.min.js"></script>

    <script src="js/bootstrap.min.js"></script>

    <script src="js/jquery.sticky.js"></script>

    <script src="js/vegas.min.js"></script>

    <script src="js/custom.js"></script>



    <!-- AFFICHER / MASQUER MOT DE PASSE -->

    <script>

        (function () {

            const password =
                document.getElementById(
                    "login-password"
                );


            const toggle =
                document.getElementById(
                    "passwordToggle"
                );


            if (!password || !toggle) {

                return;

            }


            toggle.addEventListener(

                "click",

                function () {


                    const visible =
                        password.type === "text";


                    password.type =
                        visible
                            ? "password"
                            : "text";


                    const icon =
                        this.querySelector("i");


                    icon.classList.toggle(
                        "bi-eye",
                        visible
                    );


                    icon.classList.toggle(
                        "bi-eye-slash",
                        !visible
                    );


                    this.setAttribute(

                        "aria-label",

                        visible
                            ? "Afficher le mot de passe"
                            : "Masquer le mot de passe"

                    );


                }

            );


        })();

    </script>


</body>

</html>