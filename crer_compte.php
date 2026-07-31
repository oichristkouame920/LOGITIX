<!doctype html>
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <meta name="description" content="">
        <meta name="author" content="">

        <title>Créer un compte</title>

        <!-- CSS FILES -->                
        <link href="css/fonts.css" rel="stylesheet">
        <link href="css/bootstrap.min.css" rel="stylesheet">
        <link href="css/bootstrap-icons.css" rel="stylesheet">
        <link href="css/vegas.min.css" rel="stylesheet">
        <link href="css/tooplate-barista.css" rel="stylesheet">
        <!--

        Tooplate 2137 Barista

        https://www.tooplate.com/view/2137-barista-cafe

        Bootstrap 5 HTML CSS Template

        --> 
        <style>
            body{
                background-color:chocolate;
            }

            img{
                display:inline-flex;
                height: 100px;
                width: 200px;
                align-self: flex-end;
            }

            /* ---- Sections du formulaire ---- */
            .form-section-title{
                color:#fff;
                font-weight:700;
                font-size:.95rem;
                letter-spacing:.04em;
                text-transform:uppercase;
                margin:1.6rem 0 1rem;
                padding-bottom:.5rem;
                border-bottom:1px solid rgba(255,255,255,.25);
            }
            .form-section-title:first-of-type{ margin-top:.4rem; }

            /* ---- Champs avec icône ---- */
            .field-icon{ position:relative; }
            .field-icon i{
                position:absolute;
                left:14px; top:50%;
                transform:translateY(-50%);
                color:#8a8a8a;
                font-size:.95rem;
                pointer-events:none;
            }
            .field-icon .form-control,
            .field-icon select.form-control{ padding-left:2.4rem; }
            .field-help{
                font-size:.76rem;
                color:rgba(255,255,255,.65);
                margin-top:.3rem;
            }

            /* ---- Bouton afficher / masquer mot de passe ---- */
            .password-wrap{ position:relative; }
            .password-wrap .form-control{ padding-right:2.6rem; }
            .toggle-pass{
                position:absolute; right:12px; top:50%; transform:translateY(-50%);
                background:none; border:0; color:#8a8a8a; cursor:pointer; font-size:1rem; padding:0;
            }
            .toggle-pass:hover{ color:#333; }

            /* ---- Indicateur de force du mot de passe ---- */
            .strength-meter{ height:5px; border-radius:3px; background:rgba(255,255,255,.25); margin-top:.5rem; overflow:hidden; }
            .strength-meter-bar{ height:100%; width:0%; border-radius:3px; transition:width .25s ease, background-color .25s ease; }
            .strength-label{ font-size:.76rem; margin-top:.35rem; color:rgba(255,255,255,.75); }

            .match-feedback{ font-size:.78rem; margin-top:.35rem; }
            .match-ok{ color:#8bd17c; }
            .match-bad{ color:#ff8a80; }

            /* ---- Champs entreprise conditionnels ---- */
            #bloc-entreprise{
                display:none;
                background:rgba(255,255,255,.06);
                border:1px dashed rgba(255,255,255,.3);
                border-radius:12px;
                padding:1.2rem;
                margin-top:.5rem;
            }
            #bloc-entreprise.actif{ display:block; }

            /* ---- Politique de confidentialité ---- */
            .politique{
                background-color: white;
                color: black; font-weight:400 ;
                border-radius: 15px;
                padding: 20px 20px 20px 20px;
                border-color:black;  
                border-width: 15px;              
            }

            .politique h5{
                text-align: center;
                text-decoration: underline;
            }

            .politique label{
                font-weight: bold;
            }

            .politique-consent-option{
                display:flex; align-items:center; gap:.5rem;
                border:1px solid #ddd; border-radius:8px;
                padding:.6rem 1rem; margin-bottom:.5rem;
                cursor:pointer; transition:.15s;
            }
            .politique-consent-option:hover{ background:#f7f7f7; }
            .politique-consent-option input{ margin:0; }
            .lien-politique{
                display:inline-block; margin-top:.4rem; font-size:.85rem;
                color:#8B4513; text-decoration:underline; cursor:pointer; background:none; border:0; padding:0;
            }

            #soumettre:disabled{
                background-color:#c9c9c9;
                color:#777;
                cursor:not-allowed;
                opacity:1;
            }

            .modal-content{ border-radius:12px; }
            .modal-header{ background:#01203F; }
            .modal-title{ color:#fff; font-weight:700; }
            .modal-header .btn-close{ filter:invert(1); }
            .modal-body h6{ color:#01203F; font-weight:700; margin-top:1rem; }
            .modal-body p{ font-size:.9rem; color:#333; }
        </style>
    </head>
    
    <body class="reservation-page">
                
            <main>
                <section class="booking-section section-padding">
                <div class="container">
                    <div class="row">

                        <div class="col-lg-10 col-12 mx-auto">
                            <div class="booking-form-wrap">
                                <div class="row">
                                    <div class="col-lg-12 col-12 p-0">
                                        <form class="custom-form booking-form" action="" method="post" role="form" id="formInscription" novalidate>
                                            <div class="text-center mb-4 pb-lg-2">
                                                <img src="images/logo.png" alt=""><br>
                                                <h2 class="text-white">Créer un compte</h2>
                                                <p class="text-white-50 mb-0" style="font-size:.9rem;">Quelques informations pour ouvrir votre espace client LOGITIX</p>
                                            </div>

                                            <div class="booking-form-body">

                                                <p class="form-section-title">Informations personnelles</p>

                                                <div class="row justify-content-center g-3">
                                                    <div class="col-lg-5 col-12">
                                                        <div class="field-icon">
                                                            <i class="bi bi-person"></i>
                                                            <input type="text" name="cree-form-nom" id="booking-form-name" class="form-control" placeholder="Nom" required>
                                                        </div>
                                                    </div>
                                                    <div class="col-lg-5 col-12">
                                                        <div class="field-icon">
                                                            <i class="bi bi-person"></i>
                                                            <input type="text" name="cree-form-prenom" id="cree-form-prenom" class="form-control" placeholder="Prénoms" required>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="row justify-content-center g-3 mt-1">
                                                    <div class="col-lg-5 col-12">
                                                        <div class="field-icon">
                                                            <i class="bi bi-envelope"></i>
                                                            <input type="email" name="cree-form-mail" id="cree-form-mail" class="form-control" placeholder="Email" required>
                                                        </div>
                                                    </div>
                                                    <div class="col-lg-5 col-12">
                                                        <div class="field-icon">
                                                            <i class="bi bi-telephone"></i>
                                                            <input type="tel" class="form-control" name="cree-form-phone" id="cree-form-phone" placeholder="Numéro de téléphone" pattern="[0-9]{3}-[0-9]{3}-[0-9]{4}" required>
                                                        </div>
                                                        <div class="field-help">Format : 07X-XXX-XXXX</div>
                                                    </div>
                                                </div>

                                                <div class="row justify-content-center g-3 mt-1">
                                                    <div class="col-lg-5 col-12">
                                                        <div class="field-icon">
                                                            <i class="bi bi-briefcase"></i>
                                                            <select class="form-control" name="cree-form-status" id="cree-form-status" required>
                                                                <option value="" selected disabled>Qu'êtes-vous ?</option>
                                                                <option value="particulier">Particulier</option>
                                                                <option value="entreprise">Entreprise</option>
                                                                <option value="cooperative">Coopérative / Association</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Champs conditionnels : visibles uniquement pour Entreprise / Coopérative -->
                                                <div class="row justify-content-center">
                                                    <div class="col-lg-10 col-12">
                                                        <div id="bloc-entreprise">
                                                            <p class="form-section-title" style="margin-top:0;color:#fff;">Informations professionnelles</p>
                                                            <div class="row g-3">
                                                                <div class="col-lg-6 col-12">
                                                                    <div class="field-icon">
                                                                        <i class="bi bi-building"></i>
                                                                        <input type="text" name="cree-form-raison-sociale" id="cree-form-raison-sociale" class="form-control" placeholder="Nom de l'entreprise / structure">
                                                                    </div>
                                                                </div>
                                                                <div class="col-lg-6 col-12">
                                                                    <div class="field-icon">
                                                                        <i class="bi bi-file-earmark-text"></i>
                                                                        <input type="text" name="cree-form-rccm" id="cree-form-rccm" class="form-control" placeholder="N° RCCM / Immatriculation">
                                                                    </div>
                                                                    <div class="field-help">Laisser vide si non applicable</div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <p class="form-section-title">Sécurité du compte</p>

                                                <div class="row justify-content-center g-3">
                                                    <div class="col-lg-5 col-12">
                                                        <div class="password-wrap">
                                                            <input type="password" class="form-control" name="cree-form-passwd" id="cree-form-passwd" placeholder="Mot de Passe" required>
                                                            <button type="button" class="toggle-pass" data-target="cree-form-passwd" aria-label="Afficher le mot de passe">
                                                                <i class="bi bi-eye"></i>
                                                            </button>
                                                        </div>
                                                        <div class="strength-meter"><div class="strength-meter-bar" id="strengthBar"></div></div>
                                                        <div class="strength-label" id="strengthLabel">Force du mot de passe</div>
                                                    </div>
                                                    <div class="col-lg-5 col-12">
                                                        <div class="password-wrap">
                                                            <input type="password" class="form-control" name="cree-form-confpasswd" id="cree-form-confpasswd" placeholder="Confirmer le mot de passe" required>
                                                            <button type="button" class="toggle-pass" data-target="cree-form-confpasswd" aria-label="Afficher le mot de passe">
                                                                <i class="bi bi-eye"></i>
                                                            </button>
                                                        </div>
                                                        <div class="match-feedback" id="matchFeedback">&nbsp;</div>
                                                    </div>
                                                </div>

                                                <div class="row mt-3">
                                                    <div class="politique" class="col-12 col-lg-10">  <!--Cette classe gere la politique de confidentialité-->
                                                        <h5>Politique de confidentialité</h5>

                                                        <label class="politique-consent-option" for="accepte">
                                                            <input type="radio" name="confid" id="accepte" value="accepte">
                                                            J'accepte la politique de confidentialité
                                                        </label>
                                                        <label class="politique-consent-option" for="refuse">
                                                            <input type="radio" name="confid" id="refuse" value="refuse">
                                                            Je refuse
                                                        </label>

                                                        <p class="mb-0">En cliquant sur "J'accepte", vous acceptez de vous soumettre à notre Politique de confidentialité.</p>
                                                        <button type="button" class="lien-politique" data-bs-toggle="modal" data-bs-target="#modalPolitique">
                                                            Lire la politique complète
                                                        </button>
                                                    </div>
                                                </div>

                                                <div class="row">
                                                    <div class="col-lg-4 col-md-10 col-8 mx-auto mt-3">
                                                        <button type="submit" class="form-control" id="soumettre" disabled>Créer le compte</button>
                                                    </div>
                                                </div>

                                                <!-- Piège à robots : champ invisible, doit rester vide -->
                                                <input type="text" name="site_web" id="site_web" style="position:absolute; left:-9999px;" tabindex="-1" autocomplete="off">
                                            </div>

                                        </form>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </section>


                <footer class="site-footer">
                    <div class="container">
                        <div class="row">

                            <div class="col-lg-4 col-12 me-auto">
                                <em class="text-white d-block mb-4">Où nous trouver ?</em>

                                <strong class="text-white">
                                    <i class="bi-geo-alt me-2"></i>
                                    📍 Terminal à Conteneurs de Vridi, Port Autonome d'Abidjan, Côte d'Ivoire (SIEGE)
                                </strong>

                                <ul class="social-icon mt-4">
                                    <li class="social-icon-item">
                                        <a href="https://www.facebook.com/logitix" class="social-icon-link bi-facebook">
                                        </a>
                                    </li>
        
                                    <li class="social-icon-item"><a href="https://wa.me/2250503231625?text=Bonjour j'aimerais avoir plus d'information sur vos services de livraison" class="social-icon-link bi-whatsapp"></a></li>
                                </ul>
                            </div>

                            <div class="col-lg-3 col-12 mt-4 mb-3 mt-lg-0 mb-lg-0">
                                <em class="text-white d-block mb-4">Contacts</em>

                                <p class="d-flex mb-1">
                                    <strong class="me-2">Téléphone:</strong>
                                    <a href="tel: 305-240-9671" class="site-footer-link">
                                        (+225) 
                                        05 03 23 16 25 <br>
                                        (+225)
                                        01 51 30 60 51
                                    </a>
                                </p>

                                <p class="d-flex">
                                    <strong class="me-2">Email:</strong>

                                    <a href="mailto:info@yourgmail.com" class="site-footer-link">
                                        oichristkouame920@gmail.com
                                    </a>
                                </p>
                            </div>

                            <div class="col-lg-8 col-12 mt-4">
                                <p class="copyright-text mb-0">Copyright © kouame- Design: 2025</p>
                            </div>

                    </div>
                </footer>
            </main>

            <!-- Modale : politique de confidentialité complète -->
            <div class="modal fade" id="modalPolitique" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-scrollable modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Politique de confidentialité — LOGITIX</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                        </div>
                        <div class="modal-body">
                            <p>LOGITIX s'engage à protéger les données personnelles de ses clients et utilisateurs, conformément à la réglementation en vigueur sur la protection des données.</p>

                            <h6>1. Données collectées</h6>
                            <p>Nom, prénom, adresse email, numéro de téléphone, et le cas échéant les informations relatives à votre entreprise (raison sociale, numéro d'immatriculation), collectées lors de la création de votre compte.</p>

                            <h6>2. Utilisation des données</h6>
                            <p>Vos données sont utilisées pour créer et gérer votre espace client, traiter vos demandes de devis, assurer le suivi de vos expéditions, et vous transmettre des notifications relatives à votre compte.</p>

                            <h6>3. Conservation</h6>
                            <p>Vos données sont conservées pendant toute la durée de votre relation avec LOGITIX, puis archivées ou supprimées conformément aux obligations légales applicables.</p>

                            <h6>4. Partage des données</h6>
                            <p>Vos données ne sont ni vendues, ni cédées à des tiers. Elles peuvent être partagées avec nos partenaires opérationnels (transporteurs, chauffeurs affectés) uniquement dans le cadre de l'exécution de votre expédition.</p>

                            <h6>5. Vos droits</h6>
                            <p>Vous disposez d'un droit d'accès, de rectification et de suppression de vos données. Toute demande peut être adressée à <strong>oichristkouame920@gmail.com</strong>.</p>

                            <h6>6. Sécurité</h6>
                            <p>Votre mot de passe est stocké de façon chiffrée. L'accès à votre espace client est protégé par une authentification personnelle que nous vous recommandons de garder confidentielle.</p>
                        </div>
                    </div>
                </div>
            </div>

        <!-- JAVASCRIPT FILES -->
        <script src="js/jquery.min.js"></script>
        <script src="js/bootstrap.min.js"></script>
        <script src="js/jquery.sticky.js"></script>
        <script src="js/vegas.min.js"></script>
        <script src="js/custom.js"></script>

        <script> /*Script pour griser le bouton tant que l'utilisateur n'a pas coché "J'accepte"*/
            let accepte = document.getElementById("accepte");
            let refuse = document.getElementById("refuse");
            let boutonCreer = document.getElementById("soumettre");

            function verifierAcceptation(){
                boutonCreer.disabled = !accepte.checked;
            }

            accepte.addEventListener("change", verifierAcceptation);
            refuse.addEventListener("change", verifierAcceptation);
        </script>

        <script> /* Affiche les champs entreprise si "Entreprise" ou "Coopérative" est sélectionné */
            const statutSelect = document.getElementById("cree-form-status");
            const blocEntreprise = document.getElementById("bloc-entreprise");
            const champRaisonSociale = document.getElementById("cree-form-raison-sociale");

            statutSelect.addEventListener("change", function(){
                const estProfessionnel = (this.value === "entreprise" || this.value === "cooperative");
                blocEntreprise.classList.toggle("actif", estProfessionnel);
                champRaisonSociale.required = estProfessionnel;
            });
        </script>

        <script> /* Affiche / masque le mot de passe */
            document.querySelectorAll(".toggle-pass").forEach(function(btn){
                btn.addEventListener("click", function(){
                    const champ = document.getElementById(this.getAttribute("data-target"));
                    const icone = this.querySelector("i");
                    const visible = champ.type === "text";
                    champ.type = visible ? "password" : "text";
                    icone.classList.toggle("bi-eye", visible);
                    icone.classList.toggle("bi-eye-slash", !visible);
                });
            });
        </script>

        <script> /* Indicateur de force du mot de passe + vérification de correspondance */
            const champMdp = document.getElementById("cree-form-passwd");
            const champConfMdp = document.getElementById("cree-form-confpasswd");
            const barreForce = document.getElementById("strengthBar");
            const labelForce = document.getElementById("strengthLabel");
            const retourCorrespondance = document.getElementById("matchFeedback");

            function evaluerForce(mdp){
                let score = 0;
                if (mdp.length >= 8) score++;
                if (mdp.length >= 12) score++;
                if (/[A-Z]/.test(mdp)) score++;
                if (/[0-9]/.test(mdp)) score++;
                if (/[^A-Za-z0-9]/.test(mdp)) score++;
                return score;
            }

            const etapes = [
                { largeur: "0%",   couleur: "transparent", texte: "Force du mot de passe" },
                { largeur: "20%",  couleur: "#dc3545",      texte: "Très faible" },
                { largeur: "40%",  couleur: "#dc3545",      texte: "Faible" },
                { largeur: "60%",  couleur: "#ffc107",      texte: "Moyen" },
                { largeur: "80%",  couleur: "#8bd17c",      texte: "Bon" },
                { largeur: "100%", couleur: "#28a745",      texte: "Excellent" },
            ];

            champMdp.addEventListener("input", function(){
                let score = evaluerForce(this.value);
                if (this.value !== "" && score === 0) score = 1;
                score = Math.min(score, 5);
                const etape = etapes[score];
                barreForce.style.width = etape.largeur;
                barreForce.style.backgroundColor = etape.couleur;
                labelForce.textContent = etape.texte;
                verifierCorrespondance();
            });

            function verifierCorrespondance(){
                if (champConfMdp.value === ""){
                    retourCorrespondance.innerHTML = "&nbsp;";
                    return;
                }
                if (champMdp.value === champConfMdp.value){
                    retourCorrespondance.textContent = "Les mots de passe correspondent ✓";
                    retourCorrespondance.className = "match-feedback match-ok";
                } else {
                    retourCorrespondance.textContent = "Les mots de passe ne correspondent pas";
                    retourCorrespondance.className = "match-feedback match-bad";
                }
            }

            champConfMdp.addEventListener("input", verifierCorrespondance);
        </script>

    </body>
</html>