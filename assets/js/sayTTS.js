
const TextToSpeech = {
    speed: 1,
    speedFactor: 1,
    synth: window.speechSynthesis,
    widget: null,
    text: null,

    init: function() {
      document.addEventListener('DOMContentLoaded', () => {
        this.reset();
      });

      this.speed = localStorage.getItem("speed");
      if (this.speed === null) {
        this.speed = 1;
        localStorage.setItem("speed", 1);
      }
      mylog(`speed: ${this.speed}`);
      this.initWebSpeech();

      domForEach('.pfy-say-widget', (widgetEl) => {
        this.initWidget(widgetEl);
      });
    }, // init


    initWidget: function(widgetEl) {

      // open button
      domForOne(widgetEl, '.pfy-say-open', (el) => {
        el.addEventListener('click', (ev) => {
          this.open(ev);
        });
      });

      // play button:
      domForOne(widgetEl, '.pfy-say-play', (el) => {
        el.addEventListener('click', (ev) => {
          this.play(ev);
        });
      });

      // pause button:
      domForOne(widgetEl, '.pfy-say-pause', (el) => {
        el.addEventListener('click', (ev) => {
          this.pause(ev);
        });
      });

      // stop button:
      domForOne(widgetEl, '.pfy-say-stop', (el) => {
        el.addEventListener('click', (ev) => {
          this.reset(ev);
        });
      });

    }, // initWidget

    open: function (ev) {
      this.reset();
      const btn = ev.target;
      domForOne(btn, '^.pfy-say-widget', (widget) => {
        this.widget = widget;
        mylog('open say-widget');

        let text = false;
        if (widget.dataset.callback) {
          const callback = widget.dataset.callback;
          if (typeof window[callback] === 'function') {
            text = window[callback](widget);
            if (text) {
              this.text = text;
            }
          }
        }
        if(!text) {
          const textSel = widget.dataset.sayTarget;
          domForOne(widget, textSel, (el) => {
            this.text = el.innerText;
          });
        }
        mylog(`Text to read: ${this.text}`);

        widget.classList.add('pfy-say-open');
        if (widget.classList.contains('pfy-say-autoplay')) {
          this.play();
        }

      });
    },

    play: function(ev) {
      mylog('Play');
      if (this.widget.classList.contains('pfy-say-playing')) {
        this.synth.cancel();
      }
      const utterThis = new SpeechSynthesisUtterance(this.text);
      utterThis.rate = this.speed * this.speedFactor;
      this.synth.speak(utterThis);
      this.widget.classList.add('pfy-say-playing');
      this.setButtonPressed('.pfy-say-play');
    },

    pause: function() {
      if (this.widget.classList.contains('pfy-say-paused')) {
        mylog('Resume');
        this.synth.resume();
        this.widget.classList.remove('pfy-say-paused');
        this.widget.classList.add('pfy-say-playing');
        this.unsetButtonPressed('.pfy-say-pause');
      } else {
        mylog('Pause');
        this.synth.pause();
        this.widget.classList.add('pfy-say-paused');
        this.widget.classList.remove('pfy-say-playing');
        this.setButtonPressed('.pfy-say-pause');
      }
    },

    stop: function() {
      mylog('Stop');
      this.reset();
    },

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

    setButtonPressed: function (arg) {
      let btnEl = null;
      if (typeof arg.target !== 'undefined') {
        mylog(arg.target);
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
        mylog(arg.target);
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
      } else {
        console.log("Web Speech API not supported :-(");
        document.body.classList.add('no-webspeech');
      }
    }, // initWebSpeech

}; // TextToSpeech

TextToSpeech.init();
