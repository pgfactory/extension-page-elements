/*
**  Use text-to-speech capability of browser to read aloud some text.
*
*   Usage:
*     TextToSpeech.say('Say something...');
*
*     or via Macro say()
*/


const TextToSpeech = {
    speed: 1,
    speedFactor: 1.1,
    synth: window.speechSynthesis,
    widget: null,
    text: null,
    playBtn: null,
    pauseBtn: null,
    stopBtn: null,

    init: function() {
      document.addEventListener('DOMContentLoaded', () => {
        this.reset();
      });

      this.initSpeech();
      domForEach('.pfy-tts-widget', (widgetEl) => {
        this.initWidget(widgetEl);
      });
    }, // init


    initSpeech: function () {
      this.initWebSpeech();
      const stored = localStorage.getItem("speed");
      this.speed = stored !== null ? parseFloat(stored) : 1;
      if (isNaN(this.speed)) {
        this.speed = 1;
      }
      localStorage.setItem("speed", this.speed);
    }, // initSpeech


    initWidget: function(widgetEl) {
      domForOne(widgetEl, '.pfy-tts-open', (el) => {
        el.addEventListener('click', (ev) => {
          this.open(ev);
        });
      });

      domForOne(widgetEl, '.pfy-tts-play', (el) => {
        el.addEventListener('click', () => {
          this.play();
        });
      });

      domForOne(widgetEl, '.pfy-tts-pause', (el) => {
        el.addEventListener('click', () => {
          this.pause();
        });
      });

      domForOne(widgetEl, '.pfy-tts-stop', (el) => {
        el.addEventListener('click', () => {
          this.reset();
        });
      });

      domForEach(widgetEl, '.pfy-tts-speed-wrapper input', (el) => {
        el.addEventListener('change', (ev) => {
          const value = parseFloat(ev.target.value);
          localStorage.setItem("speed", value);
          this.speed = value;
          this.play();
        });
      });
    }, // initWidget


    open: function (ev) {
      this.reset();
      const btn = ev.target;
      domForOne(btn, '^.pfy-tts-widget', (widgetEl) => {
        this.widget = widgetEl;
        this.playBtn = widgetEl.querySelector('.pfy-tts-play');
        this.pauseBtn = widgetEl.querySelector('.pfy-tts-pause');
        this.stopBtn = widgetEl.querySelector('.pfy-tts-stop');

        this.updateSpeedSelector();

        // get text to read:
        const text = executeCallbackCode(widgetEl.dataset.callback);
        if (text) {
          this.text = text;
        } else {
          this.getTextToSay(widgetEl.dataset.sayTarget, widgetEl);
        }

        // now open the widget:
        widgetEl.classList.add('pfy-tts-open');
        if (widgetEl.classList.contains('pfy-tts-autoplay')) {
          this.play();
        }
      });
    }, // open


    getTextToSay: function (textSel, widgetEl) {
      let el;
      if (textSel.includes('^')) {
        domForOne(widgetEl, textSel, (e) => {
          el = e;
        });
      } else if (widgetEl) {
        el = widgetEl.querySelector(textSel);
      }
      if (!el) {
        el = document.querySelector(textSel);
      }
      if (!el) {
        console.log(`Unable to find '${textSel}'`);
        return;
      }
      // remove the say widget in case it was embedded in the text element:
      const widgetElem = el.querySelector('.pfy-tts-widget');
      if (widgetElem) {
        const clone = el.cloneNode(true);
        clone.querySelector('.pfy-tts-widget').remove();
        this.text = clone.innerText;
      } else {
        this.text = el.innerText;
      }
      if (!this.text) {
        this.text = '';
        console.log('No text found to read aloud.');
      }
    }, // getTextToSay


    say: function(text) {
      if (!this.widget) {
        return;
      }
      this.synth.cancel();
      if (!text) {
        text = this.text;
      }
      const utterThis = new SpeechSynthesisUtterance(text);
      utterThis.rate = this.speed * this.speedFactor;
      this.synth.speak(utterThis);
    }, // say


    play: function() {
      if (!this.widget) {
        return;
      }
      this.say();
      this.widget.classList.add('pfy-tts-playing');
      this.setButtonPressed(this.playBtn);
      this.unsetButtonPressed(this.pauseBtn);
    }, // play


    pause: function() {
      if (this.synth.speaking) {
        if (this.synth.paused) {
          this.synth.resume();
          this.widget.classList.remove('pfy-tts-paused');
          this.widget.classList.add('pfy-tts-playing');
          this.unsetButtonPressed(this.pauseBtn);
          this.setButtonPressed(this.playBtn);
        } else {
          this.synth.pause();
          this.widget.classList.add('pfy-tts-paused');
          this.widget.classList.remove('pfy-tts-playing');
          this.setButtonPressed(this.pauseBtn);
          this.unsetButtonPressed(this.playBtn);
        }
      }
    }, // pause


    reset: function() {
      this.synth.cancel();
      domForEach('.pfy-tts-widget', (el) => {
        el.classList.remove('pfy-tts-open');
      });
      domForEach('.pfy-tts-widget .pfy-button', (el) => {
        el.setAttribute('aria-pressed', false);
        el.classList.remove('pfy-tts-playing', 'pfy-tts-paused', 'pfy-button-pressed');
      });
    }, // reset


    updateSpeedSelector: function() {
      if (!this.widget) {
        return;
      }
      const currSpeed = String(this.speed) + 'x';
      domForOne(this.widget, `.pfy-tts-speed-wrapper input[value="${currSpeed}"]`, (el) => {
        el.checked = true;
      });
    }, // updateSpeedSelector


    setButtonPressed: function (el) {
      if (!el) {
        return;
      }
      el.classList.add('pfy-button-pressed');
      el.setAttribute('aria-pressed', true);
    }, // setButtonPressed


    unsetButtonPressed: function (el) {
      if (!el) {
        return;
      }
      el.classList.remove('pfy-button-pressed');
      el.setAttribute('aria-pressed', false);
    }, // unsetButtonPressed


    initWebSpeech: function() {
      if ('speechSynthesis' in window) {
        this.synth = window.speechSynthesis;
      } else {
        console.log("Web Speech API not supported");
        document.body.classList.add('pfy-no-webspeech');
      }
    }, // initWebSpeech

}; // TextToSpeech

TextToSpeech.init();
