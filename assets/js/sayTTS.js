
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
      domForEach('.pfy-say-widget', (widgetEl) => {
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
      domForOne(widgetEl, '.pfy-say-open', (el) => {
        el.addEventListener('click', (ev) => {
          this.open(ev);
        });
      });
    }, // initWidget


    open: function (ev) {
        this.reset();
        const btn = ev.target;
        domForOne(btn, '^.pfy-say-widget', (widgetEl) => {
          this.widget = widgetEl;

          // setup play button:
          domForOne(widgetEl, '.pfy-say-play', (el) => {
            this.playBtn = el;
            el.addEventListener('click', (ev) => {
              this.play(ev);
            });
          });

          // setup pause button:
          domForOne(widgetEl, '.pfy-say-pause', (el) => {
            this.pauseBtn = el;
            el.addEventListener('click', (ev) => {
              this.pause(ev);
            });
          });

          // setup stop button:
          domForOne(widgetEl, '.pfy-say-stop', (el) => {
            this.stopBtn = el;
            el.addEventListener('click', (ev) => {
              this.reset(ev);
            });
          });

          // setup speed selectors:
          this.initSpeedSelector(widgetEl);

          // get text to read:
          let text = false;

          if (widgetEl.dataset.callback) {
            // case callback:
            const callback = widgetEl.dataset.callback;
            if (typeof window[callback] === 'function') {
              text = window[callback](widgetEl);
              if (text) {
                this.text = text;
              }
            }
          }
          if(!text) {
            this.getTextToSay(widgetEl.dataset.sayTarget, widgetEl);
          }
          mylog(`Text to read: ${this.text}`);

          // now open the widget:
          widgetEl.classList.add('pfy-say-open');
          if (widgetEl.classList.contains('pfy-say-autoplay')) {
            this.play();
          }

        });
    }, // open


    getTextToSay: function (textSel, widgetEl) {
      // case no callback or that didn't return any text:
      let el;
      if (textSel.match('^')) {
        domForOne(widgetEl, textSel, (e) => {
          el = e;
        });
      } else {
        if (typeof widgetEl !== 'undefined') {
          el = widgetEl.querySelector(textSel);
        } else {
          if (!el) {
            el = document.querySelector(textSel);
          }
        }
      }
      // remove the say widget in case it was embedded in the text element:
      let clone = el.cloneNode(true);
      const widgetElem = clone.querySelector('.pfy-say-widget');
      if (widgetElem) {
        widgetElem.remove();
        this.text = clone.innerText;
      } else {
        this.text = el.innerText;
      }
      if (!this.text) {
        this.text = '';
        mylog('No text found to read aloud.');
      }
    }, // getTextToSay


    play: function() {
      mylog('Play');
      if (typeof this.widget === 'undefined') {
        mylog('Play: this.widget is null');
        return;
      }
      this.synth.cancel();
      const utterThis = new SpeechSynthesisUtterance(this.text);
      utterThis.rate = this.speed * this.speedFactor;
      this.synth.speak(utterThis);
      this.widget.classList.add('pfy-say-playing');
      this.setButtonPressed(this.playBtn);
      this.unsetButtonPressed(this.pauseBtn);
    }, // play


    pause: function() {
      if (window.speechSynthesis.speaking) {
        if (window.speechSynthesis.paused) {
          mylog('Resume');
          this.synth.resume();
          this.widget.classList.remove('pfy-say-paused');
          this.widget.classList.add('pfy-say-playing');
          this.unsetButtonPressed(this.pauseBtn);
          this.setButtonPressed(this.playBtn);
        } else {
          mylog('Pause');
          this.synth.pause();
          this.widget.classList.add('pfy-say-paused');
          this.widget.classList.remove('pfy-say-playing');
          this.setButtonPressed(this.pauseBtn);
          this.unsetButtonPressed(this.playBtn);
        }
      }
    }, // pause


    stop: function() {
      mylog('Stop');
      this.reset();
    }, // stop


    reset: function() {
      this.synth.cancel();
      domForEach('.pfy-say-widget', (el) => {
        el.classList.remove('pfy-say-open');
      })
      domForEach('.pfy-say-widget .pfy-button', (el) => {
        el.setAttribute('aria-pressed', false);
        el.classList.remove('pfy-say-playing', 'pfy-say-playing', 'pfy-say-paused', 'pfy-button-pressed');
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

      domForEach(widgetEl, '.pfy-say-speed-wrapper input', (el) => {
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
        domForOne(TextToSpeech.widget, `.pfy-say-speed-wrapper input[value="${currSpeed}"]`, (el) => {
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
