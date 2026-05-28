/**
 * RevealAccordion Class
 * Handles remote-controlled <details> elements and state syncing.
 */

console.debug('reveal.js');

let pfyRevealAccordionInx = 0;
let pfyRevealAccordionListenersInitialized = false;
let pfyRevealAccordionClickListenersInitialized = false;

class RevealAccordion {
    static defaultOptions = {
      controller: '[data-reveal-target]',
        target: false,
        label: '',
        class: '',
        icon: false,
        iconRotation: '0deg,90deg',
        frame: false,
        shadow: false,
        inx: 1,
        listenersInitialized: false,
        clickListenersInitialized: false,
    };

    constructor(customOptions = {}) {
        this.options = {...RevealAccordion.defaultOptions, ...customOptions};
        this.init();
    } // constructor


  // === init =====================================================================
    init() {
        this.initControllers();
        this.setupGlobalListeners();
    } // init



    // === init controllers ======================================================
    initControllers() {
      if (!this.options.controller) {
          return;
      }
      const parent = this;
      domForAll(this.options.controller, (controllerEl) => {
        let targetSel = controllerEl.dataset.revealTarget;
        if (!targetSel) {
          if (parent.options.target) {
            controllerEl.dataset.revealTarget = parent.options.target;
            targetSel = parent.options.target;
          } else {
            console.error(`No accordion target defined`);
            return;
          }
        }
        let targetIds = '';
        domForAll(targetSel, (targetEl) => {
          let id = targetEl.id;
          if (!id) {
            id = `pfy-reveal-target-${pfyRevealAccordionInx}`;
            targetEl.id = id;
          }
          parent.prepareTargetElem(targetEl, id);
          targetIds += `${id} `;
        })

        controllerEl.setAttribute('aria-controls', targetIds.trim());
        controllerEl.classList.add('mdp-accordion-controller');
        if (this.isStatelessTriggerElement(controllerEl)) {
            controllerEl.setAttribute('aria-pressed', 'false');
            controllerEl.setAttribute('role', 'button');
        }
//        console.debug(`accordion controller initialized: #${controllerEl.id}`);
      });
    } // initControllers




  // === prepare elements =======================================================
  prepareTargetElem(targetEl, uid) {
    if (targetEl.closest('.mdp-accordion-wrapper')) {
      return; // already ininitialized
    }

    pfyRevealAccordionInx++;
    const details = this.wrapIntoDetailsElement(targetEl, uid);
    this.initRequiredFormFields(details);
    console.debug('TargetElem prepared: ', targetEl);
    targetEl.style.display = '';
    targetEl.classList.forEach(cls => {
      if (cls.includes('visually-hidden')) {
        targetEl.classList.remove(cls);
      }
    })
  } // prepareTargetElem


  wrapIntoDetailsElement(targetEl, uid) {
    const details = document.createElement('details');
    const summary = document.createElement('summary');
    const summarySpan = document.createElement('span');
    const bodyWrapper = document.createElement('div');

    details.id = uid;
    details.className = `mdp-accordion mdp-accordion-wrapper ${uid}`;

    if (this.options.class) {
      details.classList.add(this.options.class);
    }

    if (this.options.frame) {
      details.classList.add('mdp-border');
      if (typeof this.options.frame === 'string') {
        details.setAttribute('style', `--mdp-accordion-details-border-color: ${this.options.frame};`);
      }
    }

    if (this.options.shadow) {
      details.classList.add('mdp-accordion-shadow');
    }

    this.applyIconAttributes(summary);
    this.applyIconRotationAttributes(summary);

    bodyWrapper.className = 'mdp-accordion-body';
    summary.setAttribute('tabindex', '-1');
    summarySpan.textContent = this.options.label;
    summary.appendChild(summarySpan);

    targetEl.parentNode.insertBefore(details, targetEl);
    details.appendChild(summary);
    bodyWrapper.appendChild(targetEl);
    details.appendChild(bodyWrapper);

    return details;
  } // wrapIntoDetailsElement


  // === setup triggers =========================================================
    setupGlobalListeners() {
        if (pfyRevealAccordionListenersInitialized) {
            return;
        }
        pfyRevealAccordionListenersInitialized = true;

        this.setupStatefullListeners();
        this.setupStatelessListeners();
    } // setupGlobalListeners


    setupStatefullListeners() {
      ['change', 'input'].forEach((eventType) => {
        document.body.addEventListener(eventType, (ev) => {
          const el = ev.target;
          let controllerEl = el.closest('.mdp-accordion-controller');
          if (controllerEl && this.isStatelessTriggerElement(controllerEl)) {
            return; // ignore stateless triggers, they are handled separately
          }

          if (controllerEl) {
            this.handleStatefullTrigger(controllerEl);
          } else {
            // if it's not a controller, it could still be a radio button associated with a controller:
            controllerEl = this.findRelatedRadioController(el);
            if (!controllerEl) {
              return;
            }
            this.handleStatefullTrigger(controllerEl, false);
          }
        });
      });
    } // setupStatefullListeners


    setupStatelessListeners() {
      document.addEventListener('click', (ev) => {
        const controllerEl = ev.target.closest('.mdp-accordion-controller');
        if (!controllerEl || !this.isStatelessTriggerElement(controllerEl)) {
          return; // ignore stateless triggers, they are handled separately
        }

        this.handleStatelessTrigger(ev, controllerEl);
      });
    } // setupStatelessListeners



  // === trigger handlers ========================================================
    handleStatefullTrigger(controllerEl, forceState) {
      if (controllerEl.tagName === 'INPUT' && (controllerEl.type === 'radio' || controllerEl.type === 'checkbox')) {
        controllerEl = this.findRelatedRadioController(controllerEl);
      }

      if (typeof forceState === 'undefined') {
        forceState = this.evalAllControllerStates(controllerEl);
      }

      this.handleAccordionTrigger(controllerEl, forceState);
    } // handleStatefullTrigger


    handleStatelessTrigger(ev, controllerEl) {
      if (controllerEl.tagName === 'A') {
        ev.preventDefault();
      }
      const isPressed = controllerEl.getAttribute('aria-pressed') === 'true';
      controllerEl.setAttribute('aria-pressed', String(!isPressed));
      const newState = this.evalAllControllerStates(controllerEl);
      this.handleAccordionTrigger(controllerEl, newState);
    } // handleStatelessTrigger



  handleAccordionTrigger(controllerEl, newState) {
    const attrs = controllerEl.getAttribute('aria-controls');
    let targetIds;
    if (attrs && attrs.length) {
      targetIds = attrs.split(' ');
    } else {
      const dataTarget = controllerEl.dataset.revealTarget;
      if (!dataTarget) {
        return;
      }
      targetIds = dataTarget.split(' ');
    }

    targetIds.forEach((targetSel) => {
      const ch1 = targetSel.charAt(0);
      if (ch1 !== '.' && ch1 !== '#') {
        targetSel = '#' + targetSel;
      }
      domForAll(targetSel, targetEl => {
        this.setNewState(targetEl, newState);
      });
    });
  } // handleAccordionTrigger


  evalAllControllerStates(controllerEl) {
      const targets = controllerEl.dataset.revealTarget;
      let newState = false;
      domForAll('[data-reveal-target]', (el) => {
        el = this.findRelatedRadioController(el);
        const targ = el.dataset.revealTarget;
        if (targ !== targets) {
          return;
        }
        newState = newState || this.evalControllerState(el);
      });

      return newState;
    } // evalAllControllerStates


    evalControllerState(el) {
        const tag = el.tagName;

        if (tag === 'INPUT') {
            return this.evalInputControllerState(el);
        }

        if (tag === 'SELECT') {
            return el.multiple
                ? Array.from(el.selectedOptions).some((o) => o.value !== '')
                : el.value !== '';
        }

        if (tag === 'TEXTAREA') {
            return el.value.trim() !== '';
        }

        if (this.isStatelessTriggerElement(el)) {
          const pressed = el.getAttribute('aria-pressed');
          return !!pressed && pressed !== 'false';
        }

        return false;
    } // evalControllerState


    evalInputControllerState(el) {
        const type = el.getAttribute('type');

        if (type === 'checkbox' || type === 'radio') {
            return el.checked;
        }

        if (type === 'button') {
            return el.getAttribute('aria-pressed') !== 'false';
        }

        if (type === 'number' || type === 'float') {
            return !(el.value.trim() === '' || parseFloat(el.value) === 0.0);
        }

        return el.value.trim() !== '';
    } // evalInputControllerState


  findRelatedRadioController(elem) {
    let matchedController = elem;

    // for radio group: check all siblings, find the one that carries .mdp-accordion-controller:
    if (elem.type === 'radio' && elem.name) {
      domForAll(`[name=${elem.name}]`, (el) => {
        matchedController = el;
      });
    }
    return  matchedController;
  } // findRelatedRadioController


  setNewState(targetEl, newState) {
        const detailsEl = targetEl.closest('details');
        if (!detailsEl || detailsEl.open === newState) {
            return;
        }

        detailsEl.open = newState;
        this.handleRequiredFormFields(detailsEl, newState);
    } // setNewState


    initRequiredFormFields(details) {
        domForAll(details, '[required]', (el) => {
            el.dataset.required = true;
        });
    } // initRequiredFormFields


    handleRequiredFormFields(detailsEl) {
        const setRequired = detailsEl.open;

        domForAll(detailsEl, '[data-required]', (el) => {
            el.required = setRequired;
        });
    } // handleRequiredFormFields


    applyIconAttributes(summary) {
        if (!this.options.icon) {
            return;
        }

        if (this.options.icon.includes(',')) {
            const [open, closed] = this.options.icon.split(',');
            summary.setAttribute('data-icon-open', open.trim());
            summary.setAttribute('data-icon-closed', closed.trim());
            return;
        }

        summary.setAttribute('data-icon-open', this.options.icon);
        summary.setAttribute('data-icon-closed', this.options.icon);
    } // applyIconAttributes


    applyIconRotationAttributes(summary) {
        if (!this.options.iconRotation) {
            return;
        }

        if (this.options.iconRotation.includes(',')) {
            const [closed, open] = this.options.iconRotation.split(',');
            summary.setAttribute(
                'style',
                `--mdp-icon-rotation: ${closed.trim()};--mdp-icon-rotation-open: ${open.trim()};`
            );
            return;
        }

        summary.setAttribute(
            'style',
            `--mdp-icon-rotation: 0deg;--mdp-icon-rotation-open: ${this.options.iconRotation};`
        );
    } // applyIconRotationAttributes


  // stateless trigger elements: a, button, input type=button:
  isStatelessTriggerElement(el) {
    const name = el.tagName;
    return (name === 'A' || name === 'BUTTON' || (name === 'INPUT' && el.type === 'button'));
  } // isStatelessTriggerElement


  // === public helpers ==================================================
  reveal(targetEl) {
    this.setNewState(targetEl, true);
  } // reveal

  unreveal(targetEl) {
    this.setNewState(targetEl, false);
  } // unreveal

} // RevealAccordion



let pfyAccordion = null;

function pfyReveal(options) {
  pfyAccordion = new RevealAccordion(options);
}


function revealAccordion(targetEl) {
  if (pfyAccordion === null) {
    const options = RevealAccordion.defaultOptions;
    pfyAccordion = new RevealAccordion(options);
  }
  pfyAccordion.reveal(targetEl);
} // revealAccordion


function unrevealAccordion(targetEl) {
  if (pfyAccordion === null) {
    const options = RevealAccordion.defaultOptions;
    pfyAccordion = new RevealAccordion(options);
  }
  pfyAccordion.unreveal(targetEl);
} // unrevealAccordion

