(function ($) {

  "use strict";


  // NAVBAR
  $('.navbar-collapse a').on('click', function () {

    $(".navbar-collapse").collapse('hide');

  });


  // IMAGES DE LA PAGE D'ACCUEIL
  $(function () {

    var imagesAccueil = [

      "images/entrelogi.png",
      "images/entrepot1.avif",
      "images/entrepo2.avif",
      "images/chargeme.png",
      "images/hamburg-6849995_1280.jpg",
      "images/camionslogi.png",
      "images/interieur/02_preparation_commandes_02.jpg"

    ];


    /*
     * Vérifie que chaque image existe
     * avant de l'envoyer à Vegas.
     */
    var verifications = imagesAccueil.map(function (chemin) {

      return new Promise(function (resolve) {

        var image = new Image();

        image.onload = function () {

          resolve({
            src: chemin
          });

        };


        image.onerror = function () {

          console.warn(
            "Image ignorée car introuvable ou invalide :",
            chemin
          );

          resolve(null);

        };


        image.src = chemin;

      });

    });


    Promise.all(verifications).then(function (resultats) {

      /*
       * Retire les images qui n'ont pas pu
       * être chargées.
       */
      var slidesValides = resultats.filter(function (slide) {

        return slide !== null;

      });
      if (slidesValides.length === 0) {

        console.error(
          "Aucune image valide trouvée pour le carrousel LOGITIX."
        );

        return;

      }
      /*
       * Lancement Vegas
       */
      $('.hero-slides').vegas({
        slides: slidesValides,
        autoplay: true,
        loop: true,
        delay: 5000,
        timer: false,
        transition: 'fade',
        transitionDuration: 1200,
        animation: 'kenburns',
        animationDuration: 7000
      });
    });
  });
  // CUSTOM LINK
  $('.smoothscroll').click(function () {
    var el = $(this).attr('href');
    var elWrapped = $(el);
    var header_height = $('.navbar').height() + 60;
    scrollToDiv(
      elWrapped,
      header_height
    );
    return false;
    function scrollToDiv(element, navheight) {
      var offset = element.offset();
      var offsetTop = offset.top;
      var totalScroll =
        offsetTop - navheight;
      $('body,html').animate({
        scrollTop: totalScroll
      }, 300);
    }
  });
})(window.jQuery);