/*
 * Helper functions for PageElements
 */

console.debug('pe-helper.js');

window.addEventListener('DOMContentLoaded', function() {
    // To leave scroll request use:
    //      localStorage.setItem('scrollpos', parseInt(document.documentElement.scrollTop));
    // Scroll to position if request was left in localStorage:
    const yPos = localStorage.getItem('scrollpos');
    if (yPos) {
      if (!window.location.hash) { // exec only if no anchor present in url
        document.documentElement.scrollTop = yPos;
        localStorage.removeItem('scrollpos');
      }
    }
});


/**
 * Causes the browser to open a new mail window using the default mail app.
 * options:
 *  {
 *    to:
 *    cc:
 *    bcc:
 *    subject:
 *    body:
 *  }
 */
function initiateMail(options) {
  let to = options.to ?? '';
  let url = `mailto:${to}`;

  if (options.cc ?? false) {
    url = appendToUrl(url, `cc=${options.cc}`);
  }
  if (options.bcc ?? false) {
    url = appendToUrl(url, `bcc=${options.bcc}`);
  }

  if (options.subject ?? false) {
    url = appendToUrl(url, `subject=${encodeURI(options.subject)}`);
  }

  if (options.body ?? false) {
    url = appendToUrl(url, `body=${encodeURI(options.body)}`);
  }

  window.location.href = url;
} // initiateMail


function initiatePhoneCall(options) {
  let to = '';
  if (typeof options === 'string') {
    to = options;
  } else if (options.to ?? false) {
    to = options.to;
  }

  if (to) {
    to = to.replace(/\s/g, '');
    window.location.href = `tel:${to}`;
  }
} // initiatePhoneCall


/*
* Tries to execute callback function.
* callbackFun can be: js-code, name of function or closure.
* Returns null when no callback has been executed, or callback did not return a value.
* In most cases, returning true means stop further processing (i.e. default action, propagation).
*/
function executeCallbackCode(callbackFun, arg = null) {
  if (typeof callbackFun === 'undefined' || callbackFun === 'true' || !isNaN(callbackFun)) {
    return null;
  }
  let res = null;
  if (typeof callbackFun === 'function') {
    res = callbackFun( parent, parent.callbackArg );
  } else if (typeof window[callbackFun] === 'function') {
    res = window[callbackFun](arg);
  } else {
    try {
      res = new Function(callbackFun)(arg);
    } catch (e) {
      console.error(e);
      res = null;
    }
  }
  if (typeof res === 'undefined') {
    res = null;
  }
  return res;
} // executeCallbackCode


function serverLog(text, logFileName) {
  let url = appendToUrl(window.location.href, 'ajax&log=' + encodeURI(text));
  if (typeof logFileName !== 'undefined') {
    url += '&filename=' + encodeURI(logFileName);
  }
  console.log('serverLog url: ' + url);
  fetch(url, { headers: {'Content-Type': 'application/json'} });
} // serverLog


function camelize(str) {
  return str.replace(/^([A-Z])|[\s-_]+(\w)/g, function(match, p1, p2) {
    if (p2) return p2.toUpperCase();
    return p1.toLowerCase();
  });
} // camelize


function reloadAgent( arg, url, confirmMsg ) {
    let newUrl = window.location.pathname.replace(/\?.*/, '');
    if (typeof url !== 'undefined') {
        newUrl = url.trim();
        if (!newUrl || newUrl === '/') {
          newUrl = hostUrl;
        } else if (newUrl.substring(0,2) === './') {
          newUrl = pageUrl + newUrl.substring(2);
        }
    }
    if (typeof arg !== 'undefined') {
        newUrl = appendToUrl(newUrl, arg);
    }

    // leave scroll-request in localStorage:
    localStorage.setItem('scrollpos', parseInt(document.documentElement.scrollTop));

    // if sleep-overlay is present, replace img with spinner:
    const overlay = document.querySelector('.pfy-overlay-background');
    if (overlay) {
        const img = overlay.querySelector('.pfy-timeout-img');
        if (img) {
            let src = img.getAttribute('src');
            src = src.replace('sleeping.png', 'spinner.gif');
            img.setAttribute('src', src);
            img.setAttribute('style', 'width: 50px;');
        }
    }

    const doReload = function() {
        console.log('initiating page reload: "' + newUrl + '"');
        window.location.replace(newUrl);
        // force reload if hash is present in URL:
        if (newUrl.indexOf('#') !== -1) {
            window.location.reload();
        }
    };

    if (typeof confirmMsg !== 'undefined') {
        pfyConfirm(confirmMsg).then(doReload);
    } else {
        doReload();
    }
} // reloadAgent


function execAjaxPromise(cmd, options, url = false) {
  if (!url) {
    url = window.location.href;
  }
  url = url.replace(/#.*/, '');
  url = appendToUrl(url, cmd, 'ajax');
  options = options || {};
  const payload = JSON.stringify(options);
  return fetch(url, {
    method: 'POST',
    body: payload,
    headers: {
      'Content-Type': 'application/json'
    }
  })
    .then(function(response) {
      return response.json();
    });
} // execAjaxPromise


function appendToUrl(url, arg, arg2) {
    if (!arg) {
        return url;
    }
    arg = arg.replace(/^[?&]/, '');

    if (typeof arg2 !== 'undefined') {
      arg = arg + '&' + arg2;
    }

    if (url.match(/\?/)) {
        url = url + '&' + arg;
    } else {
        url = url + '?' + arg;
    }
    return url;
} // appendToUrl


/**
 * Helper function to emit a beep sound in the browser using the Web Audio API.
 *
 * @param {number} duration - The duration of the beep sound in milliseconds.
 * @param {number} frequency - The frequency of the beep sound.
 * @param {number} volume - The volume of the beep sound.
 *
 * @returns {Promise} - A promise that resolves when the beep sound is finished.
 *
 * source: https://ourcodeworld.com/articles/read/1627/how-to-easily-generate-a-beep-notification-sound-with-javascript
 */
let pfyAudioContext = null;
function beep(duration, frequency, volume){
  if (!pfyAudioContext) {
    pfyAudioContext = new AudioContext();
  }

  return new Promise((resolve, reject) => {
    duration = duration || 200;
    frequency = frequency || 440;
    volume = volume || 100;

    try{
      let oscillatorNode = pfyAudioContext.createOscillator();
      let gainNode = pfyAudioContext.createGain();
      oscillatorNode.connect(gainNode);

      oscillatorNode.frequency.value = frequency;
      oscillatorNode.type= "square";
      gainNode.connect(pfyAudioContext.destination);

      gainNode.gain.value = volume * 0.01;

      oscillatorNode.start(pfyAudioContext.currentTime);
      oscillatorNode.stop(pfyAudioContext.currentTime + duration * 0.001);

      oscillatorNode.onended = () => {
        resolve();
      };
    }catch(error){
      reject(error);
    }
  });
} // beep


/*
 * WindowFreezeOverley
 *
 * Usage:
    setupWindowFreeze(1); // in seconds
 * or
    setupWindowFreeze(2, () => {  // callback
        pfyAlert('callback has been called'); // -> asset POPUPS must be loaded
        .then(() => {
            closeWindowFreezeOverlay();
        });
        return true;
    });
 * Styling:
 *     body { --pfy-freeze-overlay-bg: #f00b; }
 */

let pfyFreezeOverlay = null;
function setupWindowFreeze(delay, callback) {
  if (typeof delay === 'number') {
    delay *= 1000;
  } else if (typeof delay === 'string') {
    const m = delay.match(/([\d.]+)\s*(\w+)/);
    if (m) {
      const unit = m[2];
      switch (unit.charAt(0).toLowerCase()) {
        case 's':
          delay = m[1] * 1000;
          break;
        case 'm':
          delay = m[1] * 60000;
          break;
        case 'h':
          delay = m[1] * 3600000;
          break;
        case 'd':
          delay = m[1] * 86400000;
          break;
      }
    }
  }

  console.debug(`starting timeout of ${delay/1000}s`);
  setTimeout(() => {
    pfyFreezeOverlay = showWindowFreezeOverlay(callback);
  }, delay)
  return this;
} // setupWindowFreeze


function showWindowFreezeOverlay(callback) {
  const imageSrc = hostAssetUrl + 'media/plugins/pgfactory/pagefactory-pageelements/icons/sleeping.webp';;
  // Prevent duplicate overlays
  const existing = document.getElementById('pfy-freeze-overlay');
  if (existing) existing.remove();

  // Overlay container
  const overlay = document.createElement('div');
  overlay.id = 'pfy-freeze-overlay';
  const img = document.createElement('img');
  img.src = imageSrc;

  overlay.appendChild(img);
  document.body.appendChild(overlay);
  console.debug('Window freeze overlay opened');
  // Fade in
  requestAnimationFrame(() => {
    overlay.style.opacity = '1';
  });

  function close() {
    const res = executeCallbackCode(callback);
    if (!res) {
      overlay.style.opacity = '0';
      setTimeout(() => overlay.remove(), 200);
      document.removeEventListener('keydown', onKeydown);
    }
  }

  function onKeydown(e) {
    if (e.key === 'Escape') close();
  }

  overlay.addEventListener('click', close);
  document.addEventListener('keydown', onKeydown);

  return overlay;
} // showWindowFreezeOverlay


function closeWindowFreezeOverlay() {
  domForAll('#pfy-freeze-overlay', (overlayEl) =>{
    overlayEl.style.opacity = '0';
    setTimeout(() => overlayEl.remove(), 200);
  })
} // closeWindowFreezeOverlay

