// SlideShow Support for PageFactory
//

const presentationCursorHideTime = 3000;

// var currentSlide = 1;

$( document ).ready(function() {

    if (!$('.pfy-presentation-support').length) {
        return;
    }

    presi = new PfyPresentationSupport();
    presi.init();

    // highlight mouse clicks:
    $('body').click(function( e ) {
      if ($('body').hasClass('selectable')) {
        return;
      }
        $('#pfy-cursor-mark').show().css({ top: (e.pageY - 24), left: (e.pageX - 24) }).addClass('pfy-wobble-cursor');
        setTimeout(function() {
            $('#pfy-cursor-mark').removeClass('pfy-wobble-cursor').hide();
        }, 700);
    });
});


function PfyPresentationSupport() {
    this.currentSlide = 1;
    this.nElements = 0;
    this.nSections = 0;
    this.mouseTimer = 0;

    this.init = function() {
        this.initPresiElements();
        this.initEventHandlers();
        this.resizeSection();
        this.revealPresentationElement();
    }; // init



    this.initPresiElements = function(){
        let $sections = $('.pfy-presentation-section');
        if (!$sections.length) {
          mylog('No presentation-sections found.');
            return; // no sections found
        }
        this.nSections = $sections.length;
        mylog('Presentation support -> sections: ' + this.nSections);

        let i = 1;
        let elInx = 1;
        $sections.each(function() {
            const $section = $( this );
            $section.addClass('pfy-withheld pfy-presentation-element pfy-presentation-element-' + elInx++);

            const $withheldElements = $('.withhold, .withhold-bullets', $section);
            nElements = $withheldElements.length;
            if (nElements) {
                $withheldElements.each(function () {
                    let $this = $( this );
                    if ($this.hasClass('withhold-bullets')) {
                        $('li', $this).each(function () {
                            $( this ).addClass('pfy-withheld pfy-presentation-element pfy-presentation-element-' + elInx++);
                        });
                    } else {
                        $this.addClass('pfy-presentation-element pfy-presentation-element-' + elInx++);
                    }
                });
            }
            mylog('Section '+i+': elements: ' + nElements);
            i++;
        });
    }; // initPresiElements



    this.initEventHandlers = function(){
        let parent = this;
        let $body = $('.pfy-presentation-support');
        $('.pfy-previous-page-link a').click(function(e) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            parent.revealPresentationElement(-1 );
        });

        $('.pfy-next-page-link a').click(function(e) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            parent.revealPresentationElement(1 );
        });

        this.mouseTimer = setTimeout(function () {
          $body.addClass('pfy-hide-cursor');
        }, presentationCursorHideTime);
        $body.mousemove(function () {
          $body.removeClass('pfy-hide-cursor');
          if (parent.mouseTimer) {
            clearTimeout(parent.mouseTimer);
          }
          parent.mouseTimer = setTimeout(function () {
            $body.addClass('pfy-hide-cursor');
          }, presentationCursorHideTime);
        });

        this.initKeyHandlers();

    }; // initEventHandlers



    this.initKeyHandlers = function() {
        let parent = this;
        let $ugLightbox = $('.ug-lightbox');
        let $activeElement = $('.pfy-current-element');
        $('body').keydown( function (e) {

            // Exceptions, where arrow keys should NOT switch page:
            if ($activeElement.closest('form').length ||	// Focus within form field
                $activeElement.closest('input').length ||	// Focus within input field
                $activeElement.closest('textarea').length ||	// Focus within textarea field
                ($ugLightbox.length && ($ugLightbox.css('display') !== 'none'))) {	// special case: ug-album in full screen mode

                mylog('in form: ' + $activeElement.closest('form').length);
                mylog('in input: ' + $activeElement.closest('input').length);
                mylog('in textarea: ' + $activeElement.closest('textarea').length);
                mylog('ug-lightbox: ' + $ugLightbox.length + ' - ' + $ugLightbox.css('display'));
                return document.defaultAction;
            }

            let keycode = e.which;

            if ((keycode === 39) || (keycode === 34)) {	// right or pgdown
              e.preventDefault();
              e.stopPropagation();
              e.stopImmediatePropagation();
              return parent.revealPresentationElement( 1 );

            } else if ((keycode === 37) || (keycode === 33)) {	// left or pgup
              e.preventDefault();
              e.stopPropagation();
              e.stopImmediatePropagation();
              return parent.revealPresentationElement( -1 );

            } else if ((keycode === 190) || (keycode === 110)) {	// . (dot)
              e.preventDefault();
              e.stopPropagation();
              e.stopImmediatePropagation();
              $('body').toggleClass('pfy-screen-off');

            // make page selectable while Option key is pressed:
            } else if (keycode === 18) {
              $('body').addClass('selectable');
            }
        });

        $('body').keyup( function (e) {
          // stop selectable when Option key is released:
          if (e.which === 18) {
            $('body').removeClass('selectable');
          }
        });
    }; // initKeyHandlers



    this.revealPresentationElement = function( which ) {
      // figure out which element to show next:
      const currentSlideNr = this.determineNext(which);
      if (!currentSlideNr) {
        return false;
      }
      mylog('revealing ' + currentSlideNr);

      // hide all sections:
      const $elements = $('.pfy-presentation-element');
      const nElems = $elements.length;
      $elements.removeClass('pfy-elem-visible pfy-current-element');

      const $currElement = $('.pfy-presentation-element-' + currentSlideNr);
      const $currSection = $currElement.closest('.pfy-presentation-section');
      const m = $currSection.attr('class').match(/pfy-presentation-element-(\d+)/);
      const currSectionNr = m[1];

      // inject page and slide numbers:
      const $pageNr = $('.pfy-page-index', $currSection);
      const pageNr = $pageNr.text();
      $('#page-nr').text(pageNr);

      // walk through elements of current section, make visible if <= currentSlideNr:
      for (let i=currSectionNr; i<currentSlideNr; i++) {
          $('.pfy-presentation-element-' + i).addClass('pfy-elem-visible');
      }

      // make current element and surrounding section visible:
      $currSection.addClass('pfy-elem-visible');
      $currElement.addClass('pfy-elem-visible pfy-current-element');
    }; // revealPresentationElement


    this.determineNext = function (which) {
      if (typeof which === 'undefined') {
        if( window.location.hash ) {
          this.currentSlide = parseInt( window.location.hash.substring(1) );
          if (this.currentSlide < 1) {
            mylog("Error: unknown element number: " + this.currentSlide);
            return false;
          }
        } else {
          if ((typeof showSlide !== 'undefined') && (showSlide == -1)) {
            this.currentSlide = $('.pfy-presentation-element').length;
          } else {
            this.currentSlide = 1;
          }
        }

      } else if (which > 0) {         // next
        this.currentSlide++;
        const $newActiveElement = $('.pfy-presentation-element-' + this.currentSlide);
        if (!$newActiveElement.length) {
          let url = $('.pfy-next-page-link a').attr('href');
          mylog('go to next page: ' + url);
          pfyReload('', url);
          return false;
        }

      } else if (which < 0) {         // previous
        this.currentSlide--;
        if (this.currentSlide <= 0) {
          let url = $('.pfy-previous-page-link a').attr('href');
          mylog('go to prev page: ' + url);
          pfyReloadPost( url, { pfySlideElem: -1 } );
          return false;
        }
      }
      $('#slide-nr').text(this.currentSlide);
      return this.currentSlide;
    } // determineNext



    this.resizeSection = function() {
        // handle fixed size provided in attribute data-font-size:
        let $fixSize = $('[data-font-size]');
        if ($fixSize.length ) {
            let fixSize = $fixSize.attr('data-font-size');
            let $section = $fixSize.closest('section');
            if ($section.length) {
                $section.css({ fontSize: fixSize });
                $('.pfy-initially-hidden').removeClass('pfy-initially-hidden').addClass('pfy-withheld-section');
                return;
            }
        }

        let fSize = 10;
        let maxFSize = 28;
        let bodyH = $('body').height();

        let $main = $( 'main' );
        let $sections = $('section');

        let mainH = $main.innerHeight();
        let vPagePaddingPx = $main.padding();
        let vPageMarginPx = $main.margin();
        let vPadding = vPagePaddingPx.top + vPagePaddingPx.bottom + vPageMarginPx.top + vPageMarginPx.bottom;
        // let mainHavail = mainH - 5;
        let mainHavail = bodyH - 5;
        mylog('bodyH: ' + bodyH + ' mainH: ' + mainH + ' vPad: ' + vPadding + ' => ' + mainHavail, false);

        $sections.hide();    // hide while determening height of each section
        let debug = $('body').hasClass('debug');
        if ( debug ) {
            $('.pfy-presentation-support').addClass('pfy-slide-visible').removeClass('pfy-initially-hidden');
        }
        const corr = 37; // -> padding top+bottom ToDo: compute that
        const m = 1.2;   //

        mainHavail -= corr;
        $sections.each(function () {
            let $section = $( this );
            $section.show();
            if ($('.no-adjust,.no-adjusting', $section).length ) {
              $section.css({fontSize: '3.3vmin', display: ''});
              return;
            }
            if ( debug ) {
                $section.css('opacity', 0.4);
            }
            // let contentH = $section.height() + corr;
            let $page = $('.pfy-page');
            let contentH = $page.height();
            let f = mainHavail / contentH;
            let diff = mainHavail - contentH;
            let fontSize = $section.css('font-size');
            fSize = parseInt(fontSize.substr(0, fontSize.length - 2));

           mylog('====== mainH: ' + mainHavail, false);
            for (let i = 0; i < 10; i++) {
                diff = mainHavail - contentH;
                fSize = fSize * f;
               mylog(i + ': diff: ' + Math.trunc( diff ) + ' fontSize: ' + Number(fSize.toFixed(1)) + ' sectionH: ' + Math.trunc( contentH ), false);
                if (Math.abs(diff) < 3) {
                    break;
                }
                $section.css({fontSize: fSize.toString() + 'pt'});
                // contentH = $section.height();
                contentH = $page.height();
                f = mainHavail / contentH;
            }
            if (fSize > maxFSize) {
                $section.css({fontSize: maxFSize.toString() + 'pt'});
            }
            $section.css('display', '');
            if ( debug ) {
                $section.css('opacity', '');
            }
        });
    }; // resizeSection

} // PfyPresentationSupport



function pfyReloadPost( url, data ) {
  let form = '';
  if (typeof data === 'string') {
    form = '<form id="lzy-tmp-form" method="post" action="' + url + '" style="display:none"><input type="hidden" name="lzy-tmp-value" value="' + data + '"></form>';
  } else if (typeof data === 'object') {
    form = '<form id="lzy-tmp-form" method="post" action="' + url + '" style="display:none">';

    for (let key in data) {
      let val = data[ key ];
      form += '<input type="hidden" name="' + key + '" value="' + val + '">';
    }

    form += '</form>';
  }
  $( 'body' ).append( form );
  $('#lzy-tmp-form').submit();
} // lzyReloadPost


