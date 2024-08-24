/*
 * Helper functions for PageElements
 */

window.onload = function() {
    // To leave scroll request use:
    //      localStorage.setItem('scrollpos', parseInt(document.documentElement.scrollTop));
    // Scroll to position if request was left in localStorage:
    const yPos = localStorage.getItem('scrollpos');
    if (yPos) {
        document.documentElement.scrollTop = yPos;
        localStorage.setItem('scrollpos', 0);
    }
}


function serverLog(text, logFileName) {
  let url = appendToUrl(window.location.href, '?ajax&log=' +  encodeURI(text));
  mylog('url: ' + url);
  if (typeof logFileName !== 'undefined') {
    url += '&filename=' + encodeURI(logFileName);
  }
  mylog('url: ' + url);
  fetch(url, { headers: {'Content-Type': 'application/json'} });
} // serverLog


function camelize(str) {
  return text.replace(/^([A-Z])|[\s-_]+(\w)/g, function(match, p1, p2, offset) {
    if (p2) return p2.toUpperCase();
    return p1.toLowerCase();
  });
} // camelize


function reloadAgent( arg, url, confirmMsg ) {
    let newUrl = window.location.pathname.replace(/\?.*/, '');
    if (typeof url !== 'undefined' && url) {
        newUrl = url.trim();
        if (!newUrl || newUrl === '/') {
          newUrl = hostUrl;
        } else if (newUrl.substring(0,2) === './') {
          newUrl = pageUrl + newUrl.substring(2);
        }
    }
    if (typeof arg !== 'undefined' && arg) {
        newUrl = appendToUrl(newUrl, arg);
    }

    // leave scroll-request in localStorage:
    localStorage.setItem('scrollpos', parseInt(document.documentElement.scrollTop));

    // if sleep-overlay is present, replace img with spinner:
    const overlay = document.querySelector('.pfy-overlay-background');
    if (overlay) {
        const img = overlay.querySelector('.pfy-timeout-img');
        if (img) {
            let url = img.getAttribute('src');
            url = url.replace('sleeping.png', 'spinner.gif');
            img.setAttribute('src', url);
            img.setAttribute('style', 'width: 50px;');
        }
        // overlay.remove();
    }

    if (typeof confirmMsg !== 'undefined') {
        pfyConfirm(confirmMsg).then(function() {
            console.log('initiating page reload: "' + newUrl + '"');
            window.location.replace(newUrl);
            // force reload if hash is present in URL:
            if (newUrl.indexOf('#') !== -1) {
                window.location.reload();
            }
        });
    } else {
        console.log('initiating page reload: "' + newUrl + '"');
        window.location.replace(newUrl);
        // force reload if hash is present in URL:
        if (newUrl.indexOf('#') !== -1) {
            window.location.reload();
        }
    }
} // reloadAgent


function execAjaxPromise(cmd, options, url = false) {
  return new Promise(function(resolve) {
    if (!url) {
      url = window.location.href;
    }
    url = url.replace(/#.*/, '');
    if (typeof url === 'undefined') {
      url = pageUrl;
    }
    url = appendToUrl(url, cmd, 'ajax');
    if (typeof options === 'undefined') {
      options = {};
    }
    const payload = JSON.stringify(options);
    fetch(url, {
      method: 'POST',
      body: payload,
      headers: {
        'Content-Type': 'application/json'
      }
    })
      .then(function(response) {
        return response.json();
      })
      .then(function(json) {
        resolve(json);
      });
  });
}



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
    // Set default duration if not provided
    duration = duration || 200;
    frequency = frequency || 440;
    volume = volume || 100;

    try{
      let oscillatorNode = pfyAudioContext.createOscillator();
      let gainNode = pfyAudioContext.createGain();
      oscillatorNode.connect(gainNode);

      // Set the oscillator frequency in hertz
      oscillatorNode.frequency.value = frequency;

      // Set the type of oscillator
      oscillatorNode.type= "square";
      gainNode.connect(pfyAudioContext.destination);

      // Set the gain to the volume
      gainNode.gain.value = volume * 0.01;

      // Start audio with the desired duration
      oscillatorNode.start(pfyAudioContext.currentTime);
      oscillatorNode.stop(pfyAudioContext.currentTime + duration * 0.001);

      // Resolve the promise when the sound is finished
      oscillatorNode.onended = () => {
        resolve();
      };
    }catch(error){
      reject(error);
    }
  });
} // beep

