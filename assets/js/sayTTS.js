
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
      this.speed = localStorage.getItem("speed");
      if (this.speed === null) {
        this.speed = 1;
        localStorage.setItem("speed", 1);
      }
      this.initWebSpeech();
    }, // initSpeech

    initWidget: function(widgetEl) {
      // init open button
      domForOne(widgetEl, '.pfy-tts-open', (el) => {
        el.addEventListener('click', (ev) => {
          this.open(ev);
        });
      });
    }, // initWidget


    open: function (ev) {
        this.reset();
        const btn = ev.target;
        domForOne(btn, '^.pfy-tts-widget', (widgetEl) => {
          this.widget = widgetEl;

          // setup play button:
          domForOne(widgetEl, '.pfy-tts-play', (el) => {
            this.playBtn = el;
            el.addEventListener('click', (ev) => {
              this.play(ev);
            });
          });

          // setup pause button:
          domForOne(widgetEl, '.pfy-tts-pause', (el) => {
            this.pauseBtn = el;
            el.addEventListener('click', (ev) => {
              this.pause(ev);
            });
          });

          // setup stop button:
          domForOne(widgetEl, '.pfy-tts-stop', (el) => {
            this.stopBtn = el;
            el.addEventListener('click', (ev) => {
              this.reset(ev);
            });
          });

          // setup speed selectors:
          this.initSpeedSelector(widgetEl);

          // get text to read:
          let text = false;
          text = executeCallbackCode(widgetEl.dataset.callback);
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
      // case no callback or that didn't return any text:
      let el;
      if (textSel.includes('^')) {
        domForOne(widgetEl, textSel, (e) => {
          el = e;
        });
      } else {
        if (typeof widgetEl !== 'undefined') {
          el = widgetEl.querySelector(textSel);
        }
      }
      if (!el) {
        el = document.querySelector(textSel);
      }
      if (!el) {
        console.log(`Unable to find '${textSel}'`);
        return;
      }
      // remove the say widget in case it was embedded in the text element:
      let clone = el.cloneNode(true);
      const widgetElem = clone.querySelector('.pfy-tts-widget');
      if (widgetElem) {
        widgetElem.remove();
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
      if (typeof this.widget === 'undefined') {
        console.log('Play: this.widget is null');
        return;
      }
      this.synth.cancel();
      if (typeof text === 'undefined') {
        text = this.text;
      }
      console.log(`Say: ${text}`);
      const utterThis = new SpeechSynthesisUtterance(text);
      utterThis.rate = this.speed * this.speedFactor;
      this.synth.speak(utterThis);
    }, // say


    play: function() {
      //console.log('Play');
      if (typeof this.widget === 'undefined') {
        console.log('Play: this.widget is null');
        return;
      }
      this.say();
      this.widget.classList.add('pfy-tts-playing');
      this.setButtonPressed(this.playBtn);
      this.unsetButtonPressed(this.pauseBtn);
    }, // play


    pause: function() {
      if (window.speechSynthesis.speaking) {
        if (window.speechSynthesis.paused) {
          console.log('Resume');
          this.synth.resume();
          this.widget.classList.remove('pfy-tts-paused');
          this.widget.classList.add('pfy-tts-playing');
          this.unsetButtonPressed(this.pauseBtn);
          this.setButtonPressed(this.playBtn);
        } else {
          console.log('Pause');
          this.synth.pause();
          this.widget.classList.add('pfy-tts-paused');
          this.widget.classList.remove('pfy-tts-playing');
          this.setButtonPressed(this.pauseBtn);
          this.unsetButtonPressed(this.playBtn);
        }
      }
    }, // pause


    stop: function() {
      console.log('Stop');
      this.reset();
    }, // stop


    reset: function() {
      this.synth.cancel();
      domForEach('.pfy-tts-widget', (el) => {
        el.classList.remove('pfy-tts-open');
      })
      domForEach('.pfy-tts-widget .pfy-button', (el) => {
        el.setAttribute('aria-pressed', false);
        el.classList.remove('pfy-tts-playing', 'pfy-tts-playing', 'pfy-tts-paused', 'pfy-button-pressed');
      });
    }, // reset


    initSpeedSelector: function (widgetEl) {
      this.speed = localStorage.getItem("speed");
      if (typeof this.speed === 'undefined') {
        this.speed = 1;
        this.speed = parseFloat(this.speed);
        localStorage.setItem("speed", 1);
      }
      this.updateSpeedSelector();

      domForEach(widgetEl, '.pfy-tts-speed-wrapper input', (el) => {
        el.addEventListener('change', (ev) => {
          const inputEl = ev.target;
          const value = parseFloat(inputEl.value);
          localStorage.setItem("speed", value);
          TextToSpeech.speed = value;
          TextToSpeech.play(ev);
        });
      });
    }, // initSpeedSelector


    updateSpeedSelector : function() {
      setTimeout(function() {
        const currSpeed = String(TextToSpeech.speed) + 'x';
        domForOne(TextToSpeech.widget, `.pfy-tts-speed-wrapper input[value="${currSpeed}"]`, (el) => {
          el.checked = true;
        });
      }, 100);
    }, // updateSpeedSelector


    setButtonPressed: function (arg) {
        let btnEl = null;
        if (typeof arg.target !== 'undefined') {
          arg.target.classList.add('pfy-button-pressed');
          arg.target.setAttribute('aria-pressed', true);

        } else if (typeof arg === 'object') {
          arg.classList.add('pfy-button-pressed');
          arg.setAttribute('aria-pressed', true);

        } else if (typeof arg === 'string') {
          domForOne(this.widget, arg, (el) => {
            el.classList.add('pfy-button-pressed');
            el.setAttribute('aria-pressed', true);
          });
        }
    }, // setButtonPressed


    unsetButtonPressed: function (arg) {
      let btnEl = null;
      if (typeof arg.target !== 'undefined') {
        arg.target.classList.remove('pfy-button-pressed');
        arg.target.setAttribute('aria-pressed', false);

      } else if (typeof arg === 'object') {
        arg.classList.remove('pfy-button-pressed');
        arg.setAttribute('aria-pressed', false);

      } else if (typeof arg === 'string') {
        domForOne(this.widget, arg, (el) => {
          el.classList.remove('pfy-button-pressed');
          el.setAttribute('aria-pressed', false);
        });
      }
    }, // unsetButtonPressed


    initWebSpeech: function() {
      if ('speechSynthesis' in window) {
        console.log("Web Speech API supported!");
        this.synth = window.speechSynthesis;
      } else {
        console.log("Web Speech API not supported :-(");
        document.body.classList.add('pfy-no-webspeech');
      }
    }, // initWebSpeech

}; // TextToSpeech

TextToSpeech.init();
