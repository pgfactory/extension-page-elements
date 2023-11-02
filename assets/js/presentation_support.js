
// SlideShow Support for PageFactory
const presentationCursorHideTime = 3000; // ms
const presentationMaxFSize = 5; // vw
var   presentationMinFSize = 2; // vw
var   presentationDefaultFSize = 3; // vw
var   presentationAutoSizing = false;
const debug = false; //document.body.classList.contains('debug');



function PfyPresentationSupport() {
  this.currentSlide = 1;
  this.nSections = 0;
  this.mouseTimer = 0;
  this.$slideNr = null;
  this.mainHavail = 0;

  this.init = function () {
    if (!document.querySelector('.pfy-large-screen')) {
      mylog('Presentation Support suspended on small screen');
      return;
    }
    if (typeof presiAutoSizing !== "undefined") {
      presentationAutoSizing = presiAutoSizing;
      mylog("AutoSizing active");
    }
    if (typeof presiDefaultFontSize !== "undefined") {
      presentationDefaultFSize = parseFloat(presiDefaultFontSize);
      presentationMinFSize = Math.min(presentationMinFSize, parseFloat(presiDefaultFontSize));
      mylog("Default fontSize: " + presentationDefaultFSize + "vw");
    }

    this.preparePresentation();
    this.initPresiElements();
    this.initEventHandlers();
    this.resizeSections();
    this.revealPresentationElement();
  }; // init


  this.preparePresentation = function () {
    const main = document.querySelector('.pfy-main');
    this.mainHavail = main.clientHeight; // optionally subtract safety margin here, e.g. - 5

    // append div#pfy-cursor-click-highlighter to body:
    const cursorMark = document.createElement('div');
    cursorMark.setAttribute('id', 'pfy-cursor-click-highlighter');
    cursorMark.style.display =  'none';
    document.body.appendChild(cursorMark);

    // inject page/slide nr elements:
    const currPgNrElem = document.querySelector('[data-currpagenr]');
    const currPageNr = currPgNrElem ? currPgNrElem.dataset.currpagenr : '';
    const pageNrElem = document.createElement('div');
    pageNrElem.setAttribute('id', 'page-nr-wrapper');
    pageNrElem.classList.add('presentation-only');
    pageNrElem.innerHTML  = '<span id="pfy-page-nr">' + currPageNr + '</span><span id="pfy-slide-nr">1</span>';
    const pfyPage = document.querySelector('.pfy-page');
    pfyPage.appendChild(pageNrElem);
    this.$slideNr = document.getElementById('pfy-slide-nr');

  }; // preparePresentation


  this.initPresiElements = function () {
    document.body.style.fontSize = presentationDefaultFSize + 'vw';
    // find all presentation-sections:
    var sections = document.querySelectorAll('.pfy-presentation-section');
    if (!sections.length) {
      mylog('No presentation-sections found.');
      return;
    }
    this.nSections = sections.length;
    mylog('Presentation support -> sections: ' + this.nSections);

    var i = 1;
    var elInx = 1;

    // loop over presentation-sections:
    sections.forEach(function (section) {
      section.classList.add('pfy-withheld', 'pfy-presentation-element', 'pfy-presentation-element-' + elInx++);

      var withheldElements = section.querySelectorAll('.withhold, .withhold-bullets');
      var nElements = withheldElements.length;

      if (nElements) {
        withheldElements.forEach(function (element) {
          if (element.classList.contains('withhold-bullets')) {
            var listItems = element.querySelectorAll('li');
            listItems.forEach(function (li) {
              li.classList.add('pfy-withheld', 'pfy-presentation-element', 'pfy-presentation-element-' + elInx++);
            });
          } else {
            element.classList.add('pfy-presentation-element', 'pfy-presentation-element-' + elInx++);
          }
        });
      }
      // mylog('Section ' + i + ': elements: ' + nElements);
      i++;
    });
  }; // initPresiElements


  this.initEventHandlers = function () {
    const parent = this;
    const body = document.querySelector('.pfy-presentation-support');

    const prevPageLink = document.querySelector('.pfy-previous-page-link a');
    if (prevPageLink) {
      prevPageLink.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        parent.revealPresentationElement(-1);
      });
    }

    const nextPageLink = document.querySelector('.pfy-next-page-link a');
    if (nextPageLink) {
      nextPageLink.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        parent.revealPresentationElement(1);
      });
    }

    this.mouseTimer = setTimeout(function () {
      body.classList.add('pfy-hide-cursor');
    }, presentationCursorHideTime);

    body.addEventListener('mousemove', function () {
      body.classList.remove('pfy-hide-cursor');

      if (parent.mouseTimer) {
        clearTimeout(parent.mouseTimer);
      }

      parent.mouseTimer = setTimeout(function () {
        body.classList.add('pfy-hide-cursor');
      }, presentationCursorHideTime);
    });

    this.activateClickMarker();

    this.initKeyHandlers();
  }; // initEventHandlers


  this.initKeyHandlers = function () {
    var parent = this;
    var ugLightbox = document.querySelector('.ug-lightbox');
    var activeElement = document.querySelector('.pfy-current-element');
    if (!activeElement) {
      activeElement = document.querySelector('.pfy-presentation-section');
      if (!activeElement) {
        return;
      }
    }

    document.body.addEventListener('keydown', function (e) {
      // Exceptions, where arrow keys should NOT switch page:
      if (
        activeElement.closest('form') ||
        activeElement.closest('input') ||
        activeElement.closest('textarea') ||
        (ugLightbox && window.getComputedStyle(ugLightbox).display !== 'none')
      ) {
        mylog('in form: ' + activeElement.closest('form'));
        mylog('in input: ' + activeElement.closest('input'));
        mylog('in textarea: ' + activeElement.closest('textarea'));
        mylog('ug-lightbox: ' + ugLightbox + ' - ' + window.getComputedStyle(ugLightbox).display);
        return document.defaultAction;
      }

      var keycode = e.key;

      if (keycode === 'ArrowRight' || keycode === 'ArrowDown' || keycode === ' ') {
        // right or pgdown or spacebar:
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        return parent.revealPresentationElement(1);

      } else if (keycode === 'ArrowLeft' || keycode === 'ArrowUp') {
        // left or pgup:
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        return parent.revealPresentationElement(-1);

      } else if (keycode === '.') {
        // . (dot):
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        document.body.classList.toggle('pfy-screen-off');

      } else if (keycode === 'Alt') {
        // make page selectable while Option key is pressed:
        document.body.classList.add('pfy-selectable');
      }
    });

    document.body.addEventListener('keyup', function (e) {
      // stop selectable when Option key is released:
      if (e.key === 'Alt') {
        document.body.classList.remove('pfy-selectable');
      }
    });
  }; // initKeyHandlers


  this.revealPresentationElement = function (which) {
    // figure out which element to show next:
    var currentSlideNr = this.determineNext(which);
    if (!currentSlideNr) {
      return false;
    }
    mylog('revealing ' + currentSlideNr);

    // hide all sections:
    var elements = document.querySelectorAll('.pfy-presentation-element');

    elements.forEach(function (element) {
      element.classList.remove('pfy-elem-visible', 'pfy-current-element');
    });

    var currElement = document.querySelector('.pfy-presentation-element-' + currentSlideNr);
    if (!currElement) {
      return;
    }
    var currSection = currElement.closest('.pfy-presentation-section');
    var m = currSection.className.match(/pfy-presentation-element-(\d+)/);
    var currSectionNr = m[1];

    // inject page and slide numbers:
    const pageNrElem = currSection.querySelector('.pfy-page-index');
    if (pageNrElem) {
      this.$pageNr.innerText = pageNrElem.innerText;
    }
    // walk through elements of current section, make visible if <= currentSlideNr:
    for (var i = currSectionNr; i < currentSlideNr; i++) {
      document.querySelectorAll('.pfy-presentation-element-' + i).forEach(function (element) {
        element.classList.add('pfy-elem-visible');
      });
    }

    // make current element and surrounding section visible:
    currSection.classList.add('pfy-elem-visible');
    currElement.classList.add('pfy-elem-visible', 'pfy-current-element');
  }; // revealPresentationElement


  this.determineNext = function (which) {
    if (typeof which === 'undefined') {
      if (window.location.hash) {
        this.currentSlide = parseInt(window.location.hash.substring(1)) + 1;
        if (this.currentSlide < 1) {
          mylog('Error: unknown element number: ' + this.currentSlide);
          return false;
        }
      } else {
        this.currentSlide = 1;
      }
    } else if (which > 0) {
      // next
      this.currentSlide++;
      const newActiveElement = document.querySelector('.pfy-presentation-element-' + this.currentSlide);
      if (!newActiveElement) {
        const urlElem = document.querySelector('.pfy-next-page-link a');
        if (urlElem) {
          const url = urlElem.getAttribute('href');
          mylog('go to next page: ' + url);
          reloadAgent('', url);
          return false;
        } else {
          beep(50, 200, 20);
          this.currentSlide--;
        }
      }
    } else if (which < 0) {
      // previous
      this.currentSlide--;
      if (this.currentSlide <= 0) {
        const prevLink = document.querySelector('.pfy-previous-page-link a');
        if (prevLink) {
          var url = prevLink.getAttribute('href');
          mylog('go to prev page: ' + url);
          pfyReloadPost(url, {pfySlideElem: -1});
        } else {
          beep(50, 200, 20);
          this.currentSlide++;
        }
        return false;
      }
    }
    this.$slideNr.textContent = this.currentSlide-1;
    return this.currentSlide;
  }; // determineNext


  this.resizeSections = function () {
    const parent = this;
    const sections = document.querySelectorAll('section');
    if (!sections) {
      return;
    }
    sections.forEach(function (section) {
      parent.resizeSection(section);
    });
  }; //resizeSections


  this.resizeSection = function (section) {
    // section.style.opacity = 0.5;
    section.style.opacity = 0;
    section.style.display = 'block';

    if (presentationAutoSizing) {
      this.autoResizeSection(section);
    } else {
      this.resizeSectionsOnRequest(section);
    }
    this.resizeImages(section);
    section.style.opacity = 0;
    section.style.display = 'none';
  }; // resizeSection


  this.resizeSectionsOnRequest = function (section) {
    const doAdjust = section.classList.contains('auto') ||
        section.querySelector('.auto, .auto-adjust');
    if (!doAdjust) {
      return;
    }
    this.doResizeSection(section);
  }; // resizeSectionsOnRequest


  this.autoResizeSection = function (section) {
    const noAutoAdjust = section.classList.contains('no-adjust') || section.querySelector('.no-adjust');
    if (noAutoAdjust) {
      section.style.fontSize = presentationDefaultFSize + 'vw';
      if (debug) { mylog(`no-adjust: fSize: ${presentationDefaultFSize}vw`); }
      return;
    }
    this.doResizeSection(section);
  }; // autoResizeSection


  this.doResizeSection = function (section) {
    const mainHavail = this.mainHavail;

    let contentH = 0;
    fSize = presentationMinFSize;

    this.hideImages(section);

    section.style.fontSize = fSize + 'vw';
    contentH = section.offsetHeight;
    if (debug) { mylog(`mainHavail: ${mainHavail}  fSize: ${fSize}vw`); }

    if (contentH > mainHavail) { // case 1: content too big for smallest fontsize
      mylog("Too much content - scrolling will be necessary");

    } else {
      fSize = presentationMaxFSize;
      section.style.fontSize = fSize + 'vw';
      contentH = section.offsetHeight;
      if (contentH < mainHavail) { // case 2: content too small to fill space
        mylog("Little content - using largest possible fontsize");

      } else {
        let step = (presentationMaxFSize - presentationMinFSize) / 2;
        let diff = mainHavail - contentH;
        let absDiff = Math.abs(diff);
        let prevDiff = 99999;

        for (var i = 0; i < 10; i++) {
          fSize += step * Math.sign(diff);
          if (absDiff < prevDiff) {
            step *= 0.5;
          } else {
            step *= 0.75;
          }

          if (debug) { mylog(`i: ${i}  absDiff: ${absDiff} fSize: ${fSize}vw  contentH: ${contentH} step: ${step}`); }
          section.style.fontSize = fSize + 'vw';
          contentH = section.offsetHeight;
          prevDiff = absDiff;
          diff = mainHavail - contentH;
          absDiff = Math.abs(diff);
          if (absDiff <= 10) {
            if (debug) { mylog(`Solution found: i: ${i}  absDiff: ${absDiff} fSize: ${fSize}vw  contentH: ${contentH} step: ${step}`); }
            break;
          }
        }
        mylog("Fontsize: " + fSize);
      }
    }
  }; // doResizeSection


  this.hideImages = function(section) {
    const images = section.querySelectorAll('.pfy-img-wrapper .pfy-img');
    if (images) {
      images.forEach(function(image) {
        image.style.display = 'none';
      });
    }
  };


  this.resizeImages = function (section) {
    const images = section.querySelectorAll('.pfy-img-wrapper .pfy-img');
    if (images) {
      const parent = this;
      images.forEach(function(image) {
        parent.resizeImage(section, image);
      });
    }
  }; // resizeImages


  this.resizeImage = function (section, image) {
    const mainHavail = this.mainHavail;
    let maxHeight = mainHavail * 0.6;
    const step = mainHavail * 0.02;
    const contentH0 = section.offsetHeight;
    let contentH = 0;
    let lastMaxHeight = 0;

    image.style.display = '';
    image.style.maxHeight = maxHeight + 'px';


    for (var i = 0; i < 30; i++) {
      contentH = section.offsetHeight;
      if (contentH <= contentH0) {
        lastMaxHeight = maxHeight;
        maxHeight += step;
        image.style.maxHeight = maxHeight + 'px';
      } else {
        break;
      }
    }
    mylog(`image resized in ${i} steps`);
    image.style.maxHeight = lastMaxHeight + 'px';
  }; // resizeImage


  this.activateClickMarker = function () {
    // Highlight mouse clicks
    document.body.addEventListener('click', function (e) {
      if (document.body.classList.contains('pfy-selectable')) {
        return;
      }

      let pfyCursorMark = document.querySelector('#pfy-cursor-click-highlighter');
      if (pfyCursorMark) {
        pfyCursorMark.style.display = 'block';
        pfyCursorMark.style.top = e.pageY - 24 + 'px';
        pfyCursorMark.style.left = e.pageX - 24 + 'px';
        pfyCursorMark.classList.add('pfy-wobble-cursor');

        setTimeout(function () {
          pfyCursorMark.classList.remove('pfy-wobble-cursor');
          pfyCursorMark.style.display = 'none';
        }, 700);
      }
    });
  }; // activateClickMarker

} // PfyPresentationSupport



function pfyReloadPost(url, data) {
  var form = '';

  if (typeof data === 'string') {
    form = '<form id="lzy-tmp-form" method="post" action="' + url + '" style="display:none"><input type="hidden" name="lzy-tmp-value" value="' + data + '"></form>';
  } else if (typeof data === 'object') {
    form = '<form id="lzy-tmp-form" method="post" action="' + url + '" style="display:none">';

    for (var key in data) {
      var val = data[key];
      form += '<input type="hidden" name="' + key + '" value="' + val + '">';
    }

    form += '</form>';
  }

  document.body.insertAdjacentHTML('beforeend', form);
  document.getElementById('lzy-tmp-form').submit();
} // pfyReloadPost


// when page ready -> initialize:
document.addEventListener('DOMContentLoaded', function () {
  if (document.querySelector('.pfy-presentation-support')) {
    var presi = new PfyPresentationSupport();
    presi.init();
  }
}); // DOMContentLoaded

