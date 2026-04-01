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
        controller: '.mdp-accordion-controller',
        target: '.mdp-accordion-target',
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

    static interactiveControllerTags = ['A', 'BUTTON'];

    constructor(customOptions = {}) {
        this.options = {...RevealAccordion.defaultOptions, ...customOptions};
        this.init();
    } // constructor


    init() {
        const targetIds = this.prepareTargetElems();
        this.initControllers(targetIds);
        this.setupGlobalListeners();
    } // init

    prepareTargetElems() {
        const targetIds = [];

        domForAll(this.options.target, (targetEl) => {
            pfyRevealAccordionInx++;
            const uid = `pfy-reveal-${pfyRevealAccordionInx}`;
            const details = this.wrapIntoDetailsElement(targetEl, uid);
            this.initRequiredFormFields(details);
            targetIds.push(uid);
        });

        return targetIds.join(' ');
    } // prepareTargetElems


    initControllers(targetIds) {
      if (!this.options.controller) {
          return;
      }
      domForAll(this.options.controller, (controllerEl) => {
          controllerEl.setAttribute('aria-controls', targetIds);
          controllerEl.classList.add('mdp-accordion-controller');
          console.debug(`accordion controller initialized: #${controllerEl.id}`);
          if (this.isClickableElement(controllerEl)) {
              controllerEl.setAttribute('aria-pressed', 'false');
              controllerEl.setAttribute('role', 'button');
              this.setupGlobalClickListeners();
          }
      });
    } // initControllers


    isClickableElement(el) {
        return (RevealAccordion.interactiveControllerTags.includes(el.tagName) ||
          (el.tagName === 'INPUT' && el.type === 'button'));
    } // isClickableElement


    setupGlobalListeners() {
        if (pfyRevealAccordionListenersInitialized) {
            return;
        }
        pfyRevealAccordionListenersInitialized = true;

        ['change', 'input'].forEach((eventType) => {
            document.body.addEventListener(eventType, (ev) => {
              const el = ev.target;
              if (eventType === 'input' && (el.type === 'radio' || el.type === 'checkbox')) {
                return;
              } else if (eventType === 'change' && !(el.type === 'radio' || el.type === 'checkbox')) {
                return;
              }
              let controllerEl = el.closest('.mdp-accordion-controller');
              if (controllerEl) {
                this.handleAccordionTrigger(controllerEl);
              } else {
                // if it's not a controller, it could still be a radio button associated with a controller:
                controllerEl = this.resolveTriggeredController(el);
                if (!controllerEl) {
                  return;
                }
                this.handleAccordionTrigger(controllerEl, false);
              }
            });
        });
    } // setupGlobalListeners


    resolveTriggeredController(elem) {
      let matchedController = null;
      if (elem.type === 'radio' && elem.name) {
            domForOne(`[name=${elem.name}]`, (el) => {
                if (!matchedController && el.classList.contains('mdp-accordion-controller')) {
                  matchedController = el;
                }
            });
        }
        return  matchedController;
    } // resolveTriggeredController


    setupGlobalClickListeners() {
        if (pfyRevealAccordionClickListenersInitialized) {
            return;
        }
        pfyRevealAccordionClickListenersInitialized = true;

        document.addEventListener('click', (ev) => {
            const controllerEl = ev.target.closest('.mdp-accordion-controller');
            if (!controllerEl || !this.isClickableElement(controllerEl)) {
                return;
            }

            if (controllerEl.tagName === 'A') {
                ev.preventDefault();
            }

            const isPressed = controllerEl.getAttribute('aria-pressed') !== 'false';
            controllerEl.setAttribute('aria-pressed', String(!isPressed));
            this.handleAccordionTrigger(controllerEl);
        });
    } // setupGlobalClickListeners


    handleAccordionTrigger(controllerEl, newState) {
        if (typeof newState === 'undefined') {
          newState = this.evalAllControllerStates();
        }
        const targetIds = controllerEl.getAttribute('aria-controls').split(' ');

        targetIds.forEach((targetId) => {
            const targetEl = document.getElementById(targetId);
            this.setNewState(targetEl, newState);
        });
    } // handleAccordionTrigger


    evalAllControllerStates() {
      if (!this.options.controller) {
        return false;
      }

      let newState = false;
      domForAll(this.options.controller, (el) => {
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

        if (RevealAccordion.interactiveControllerTags.includes(tag)) {
            return el.getAttribute('aria-pressed') !== 'false';
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


    wrapIntoDetailsElement(targetEl, uid) {
        const details = document.createElement('details');
        const summary = document.createElement('summary');
        const summarySpan = document.createElement('span');
        const bodyWrapper = document.createElement('div');

        details.id = uid;
        details.className = `mdp-accordion ${uid}`;

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
} // RevealAccordion
