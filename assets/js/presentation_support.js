
// SlideShow Support for PageFactory
const presentationCursorHideTime = 3000; // ms
const presentationMaxFSize = 5; // vw
var   presentationMinFSize = 1.75; // vw
var   presentationDefaultFSize = 3; // vw
var   presentationAutoSizing = false;
const debug = false; //document.body.classList.contains('debug');



function PfyPresentationSupport() {
  this.currentSlide = 1;
  this.nSections = 0;
  this.nElements = 0;
  this.mouseTimer = 0;
  this.mainHavail = 0;
  this.moved = false;
  this.speakerWindow;

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
    if (!presentationActive) {
      return;
    }
    this.openSpeakerNotes();
    this.initPresiElements();
    this.initEventHandlers();
    this.resizeSections();
    const parent = this;
    setTimeout(function () {
      parent.revealPresentationElement();
    }, 500);
  }; // init


  this.preparePresentation = function () {
    const main = document.querySelector('.pfy-main');
    this.mainHavail = main.clientHeight; // optionally subtract safety margin here, e.g. - 5

    // append div#pfy-cursor-click-highlighter to body:
    const cursorMark = document.createElement('div');
    cursorMark.setAttribute('id', 'pfy-cursor-click-highlighter');
    cursorMark.style.display =  'none';
    document.body.appendChild(cursorMark);
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

    var i = 0;
    var elInx = 1;
    const parent = this;

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
      i++;
    });
    this.nElements = elInx-1;
  }; // initPresiElements


  this.initEventHandlers = function () {
    const parent = this;
    const body = document.querySelector('.pfy-presentation-active');

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
        document.querySelector('.baguetteBox-open') ) {
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
      }
    });
  }; // initKeyHandlers


  this.revealPresentationElement = function (which) {
    // figure out which element to show next:
    const currentSlideNr = this.determineNext(which);
    if (!currentSlideNr) {
      return false;
    }
    mylog('revealing ' + currentSlideNr);

    // hide all sections:
    const elements = document.querySelectorAll('.pfy-presentation-element');
    if (elements) {
      elements.forEach(function (element) {
        element.classList.remove('pfy-elem-visible', 'pfy-current-element');
      });
    }

    const currElement = document.querySelector('.pfy-presentation-element-' + currentSlideNr);
    if (!currElement) {
      return;
    }
    const currSection = currElement.closest('.pfy-presentation-section');
    const m = currSection.className.match(/pfy-presentation-element-(\d+)/);
    const currSectionNr = m[1];

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
    this.sendSpeakerNotes(currSection, currElement);

    // set browser address:
    const h1Elem = currSection.querySelector('h1');
    const sectionNr = h1Elem.getAttribute('id');
    if (!sectionNr) {
      return;
    }
    const slideNr = (i - currSectionNr + 1);
    const url = window.location.href.replace(/#.*/, '');
    let hash = '';
    if (slideNr === 0) {
      hash = '#' + sectionNr;
    } else {
      hash = '#' + sectionNr + '.' + slideNr;
    }
    window.history.replaceState({}, '', url + hash);
  }; // revealPresentationElement


  this.determineNext = function (which) {
    if (typeof which === 'undefined') {
      if (window.location.hash) {
        this.currentSlide = 0;
        const hash = window.location.hash;
        if (hash === '#-1') {
          this.currentSlide = this.nElements;
          const url = window.location.href.replace(/#.*/, '');
          window.history.replaceState({}, '', url);
          return this.currentSlide;
        }
        let  id;
        let  subInx = false;

        // check for pattern url#x.y:
        const m = hash.match(/#(\d+)(\.(\d+))?/);
        if (m) {
          id = m[1];
          subInx = parseInt(m[3]) - 1;
        } else {
          id = hash.substring(1);
        }

        const aElem = document.getElementById(id);
        if (aElem) {
          const parentSection = aElem.closest('.pfy-section-wrapper');
          if (parentSection) {
            const styles = parentSection.classList.value;
            const m = styles.match(/pfy-presentation-element-(\d+)/);
            if (m[1]) {
              this.currentSlide = parseInt(m[1]);
              if (subInx) { // add optional sub-index
                this.currentSlide += subInx;
              }
            }
          }
        }
        if (this.currentSlide < 1) {
          mylog('Error: unknown element number: ' + this.currentSlide);
          return false;
        }
      } else {
        this.currentSlide = 1;
      }

    // case move to next:
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

    // case move to prev:
    } else if (which < 0) {
      // previous
      this.currentSlide--;
      if (this.currentSlide <= 0) {
        const prevLink = document.querySelector('.pfy-previous-page-link a');
        if (prevLink) {
          const url = prevLink.getAttribute('href') + '#-1';
          mylog('go to prev page: ' + url);
          window.location.href = url;
        } else {
          beep(50, 200, 20);
          this.currentSlide++;
        }
        return false;
      }
    }
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
    section.style.opacity = 0.5;
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
      // apply default unless font-size already set on section element:
      if (!section.style.fontSize) {
        section.style.fontSize = presentationDefaultFSize + 'vw';
        if (debug) {
          mylog(`no-adjust: fSize: ${presentationDefaultFSize}vw`);
        }
      }
      return;
    }
    this.doResizeSection(section);
  }; // autoResizeSection


  this.doResizeSection = function (section) {
    const title = section.querySelector('h1');
    let startFSize = presentationMaxFSize;
    // check whether section already has a font-size applied:
    if (section.style.fontSize) {
      startFSize = section.style.fontSize;
      startFSize = parseInt(startFSize.replace(/\D/g,''));
    }

    const mainHavail = this.mainHavail;

    let contentH = 0;
    let fSize = presentationMinFSize;

    this.hideImages(section);

    section.style.fontSize = fSize + 'vw';
    contentH = section.offsetHeight;
    if (debug) { mylog(`mainHavail: ${mainHavail}  fSize: ${fSize}vw`); }

    if (contentH > mainHavail) { // case 1: content too big for smallest fontsize
      mylog(`Fontsize: ${fSize} (smallest fontsize)`);
      const mainWrapper = document.querySelector('.pfy-main');
      if (mainWrapper) {
        mainWrapper.classList.add('pfy-scroll-hints');
      }

    } else {
      fSize = startFSize;
      section.style.fontSize = fSize + 'vw';
      contentH = section.offsetHeight;
      if (contentH < mainHavail) { // case 2: content too small to fill space
        mylog(`Fontsize: ${fSize} (largest fontsize)`);

      } else {
        let step = (startFSize - presentationMinFSize) / 2;
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
    this.unhideImages(section);
  }; // doResizeSection


  this.hideImages = function(section) {
    const images = section.querySelectorAll('.pfy-img');
    if (images) {
      images.forEach(function(image) {
        image.style.display = 'none';
      });
    }
  }; // hideImages


  this.unhideImages = function(section) {
    const images = section.querySelectorAll('.pfy-img');
    if (images) {
      images.forEach(function(image) {
        image.style.display = null;
      });
    }
  }; // unhideImages


  this.resizeImages = function (section) {
    const images = section.querySelectorAll('.pfy-img');
    // const images = section.querySelectorAll('.pfy-img-wrapper');
    // const images = section.querySelectorAll('.pfy-img-wrapper .pfy-img');
    if (images) {
      this.hideImages(section);
      const parent = this;
      images.forEach(function(image) {
        parent.resizeImage(section, image);
      });
      this.unhideImages(section);
    }
  }; // resizeImages


  this.resizeImage = function (section, image) {
    let parentNode = image.parentNode;
    let containerWidth = null;
    let containerHeight = null;
    let imgWrapper = image.closest('.pfy-img-wrapper');
    let captionHeight = 0;

    // check whether pfy-img-wrapper exists, wrap if necessary:
    if (!imgWrapper) {
      const node = document.createElement("div");
      node.classList.add('pfy-img-wrapper');
      this.wrap(image, node);
      imgWrapper = parentNode.querySelector('.pfy-img-wrapper');
      image = imgWrapper.querySelector('.pfy-img');
    } else {
      // wrapper exists:
      parentNode = parentNode.parentNode;
      // image.style.display = 'none';
      // containerWidth = parentNode.offsetWidth;
      // containerHeight = parentNode.offsetHeight;
      // image.style.display = 'block';
      const caption = parentNode.querySelector('figcaption');
      if (caption) {
        captionHeight = caption.offsetHeight;
      }
    }

    // determine height of wrapper without image interfering:
    // image.style.display = 'none';
    containerWidth = parentNode.offsetWidth;
    containerHeight = parentNode.offsetHeight;
    // image.style.display = null;

    imgWrapper.style.width = containerWidth + 'px';
    imgWrapper.style.height = containerHeight + 'px';

    image.style.maxWidth = containerWidth + 'px';
    image.style.maxHeight = (containerHeight - captionHeight) + 'px';
  }; // resizeImage


  this.wrap = function (el, wrapper) {
    if (el && el.parentNode) {
      el.parentNode.insertBefore(wrapper, el);
      wrapper.appendChild(el);
    }
  }; // wrap


  this.activateClickMarker = function () {
    // Highlight mouse clicks
    const parent = this;
    document.body.addEventListener('mousedown', () => {
      parent.moved = false
    })
    document.body.addEventListener('mousemove', () => {
      parent.moved = true
    })
    document.body.addEventListener('mouseup', (event) => {
      if (!parent.moved) {
        parent.showClickMarker(event);
      }
    })
  }; // activateClickMarker


  this.showClickMarker = function (event) {
    let pfyCursorMark = document.querySelector('#pfy-cursor-click-highlighter');
    if (pfyCursorMark) {
      pfyCursorMark.style.display = 'block';
      pfyCursorMark.style.top = event.pageY - 24 + 'px';
      pfyCursorMark.style.left = event.pageX - 24 + 'px';
      pfyCursorMark.classList.add('pfy-wobble-cursor');

      setTimeout(function () {
        pfyCursorMark.classList.remove('pfy-wobble-cursor');
        pfyCursorMark.style.display = 'none';
      }, 700);
    }
  }; // showClickMarker


  this.openSpeakerNotes = function () {
    const url = hostUrl + 'presentation_speaker_notes/'; // corresponds to index.php
    this.speakerWindow = window.open(url, "SpeakerNotes", "width=800,height=600");
    if (!this.speakerWindow) {
      alert("Problem opening speakerWindow.\nPoss. Popup is inhibited by browser.");
    }
  }; // openSpeakerNotes


  this.sendSpeakerNotes = function (currSection, currElement) {
    const notesElem = currElement.querySelector('.notes');
    if (notesElem) {
      const content = notesElem.innerHTML;
      this.speakerWindow.postMessage(content, "*");
    }
  }; // sendSpeakerNotes

} // PfyPresentationSupport



// when page ready -> initialize:
document.addEventListener('DOMContentLoaded', function () {
  if (document.querySelector('.pfy-presentation-active')) {
    var presi = new PfyPresentationSupport();
    presi.init();
  }
}); // DOMContentLoaded

