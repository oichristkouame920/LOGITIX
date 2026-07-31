(function ($) {
  "use strict";
    // NAVBAR
    $('.navbar-collapse a').on('click',function(){
      $(".navbar-collapse").collapse('hide');
    });

    $(function() {// pour les images qui sont sur la page d'acceuil
      $('.hero-slides').vegas({ 
          slides: [
            {src:"images/entrelogi.png"},
            {src:"images/entrepot1.avif"},
            {src:"images/entrepo2.avif"},
            {src:"images/chargeme.png"},
            {src:"images/hamburg-6849995_1280.jpg"},
            {src:"images/camionslogi.png"},
            {src:"images/entrepot-dakar.jpg"}.
          ],
          timer: false,
          animation: 'kenburns',
      });
    });
    
    // CUSTOM LINK
    $('.smoothscroll').click(function(){
      var el = $(this).attr('href');
      var elWrapped = $(el);
      var header_height = $('.navbar').height() + 60;
  
      scrollToDiv(elWrapped,header_height);
      return false;
  
      function scrollToDiv(element,navheight){
        var offset = element.offset();
        var offsetTop = offset.top;
        var totalScroll = offsetTop-navheight;
  
        $('body,html').animate({
        scrollTop: totalScroll
        }, 300);
      }
    });
  
  })(window.jQuery);