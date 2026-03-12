/**
 * RevealAccordion Class
 * Handles remote-controlled <details> elements and state syncing.
 */

class RevealAccordion {
  static defaultOptions = {
    controller: ".mdp-accordion-controller",
    target: ".mdp-accordion-target",
    label: "",
    class: '',
    icon: false,
    iconRotation: '0deg,90deg',
    frame: false,
    shadow: false,
    inx: 1,
  };

  static listenersAttached = false;

  constructor(customOptions = {}) {
    this.options = { ...RevealAccordion.defaultOptions, ...customOptions };
    this.init();
  }

  /**
   * Initialize the accordion elements and linking
   */
  init() {
    const controllers = document.querySelectorAll(this.options.controller);
    const targetDiv = document.querySelector(this.options.target);

    if (!targetDiv) return;

    const uid = `remote-${Math.random().toString(36).substring(2, 9)}`;

    // Create the <details> structure
    const details = this.createDetails(uid);
    const summary = this.createSummary();
    const bodyWrapper = document.createElement('div');
    bodyWrapper.className = 'mdp-accordion-body';

    // Assemble DOM
    targetDiv.parentNode.insertBefore(details, targetDiv);
    details.appendChild(summary);
    bodyWrapper.appendChild(targetDiv);
    details.appendChild(bodyWrapper);

    // Link controllers to the new ID
    if (controllers.length) {
      controllers.forEach(ctrl => {
        ctrl.setAttribute('data-controls-details', uid);
        if (['A', 'BUTTON'].includes(ctrl.tagName)) {
          ctrl.setAttribute('aria-expanded', 'false');
          ctrl.setAttribute('role', 'button');
        }
      });
    }

    // Initial state sync
    RevealAccordion.updateDetailsState(details, uid);

    // Attach global listeners only once per page load
    RevealAccordion.attachGlobalListeners();
  } // init


  /**
   * Create and configure the <details> element
   */
  createDetails(uid) {
    const details = document.createElement('details');
    details.id = uid;
    details.className = 'mdp-accordion';

    if (this.options.class) {
      details.classList.add(this.options.class);
    }
    if (this.options.frame) {
      details.classList.add('mdp-border');
      if (typeof this.options.frame === 'string') {
        details.style.setProperty('--mdp-accordion-details-border-color', this.options.frame);
      }
    }
    if (this.options.shadow) {
      details.classList.add('mdp-accordion-shadow');
    }

    return details;
  } // createDetails


  /**
   * Create and configure the <summary> element
   */
  createSummary() {
    const summary = document.createElement('summary');
    const summarySpan = document.createElement('span');

    summary.setAttribute('tabindex', '-1');
    summarySpan.textContent = this.options.label;
    summary.appendChild(summarySpan);

    if (typeof this.options.icon === 'string') {
      const [open, closed = open] = this.options.icon.split(',');
      summary.setAttribute('data-icon-open', open.trim());
      summary.setAttribute('data-icon-closed', closed.trim());
    }

    if (this.options.iconRotation) {
      const rotation = String(this.options.iconRotation);
      if (rotation.includes(',')) {
        const [closed, open] = rotation.split(',');
        summary.style.setProperty('--mdp-icon-rotation', closed.trim());
        summary.style.setProperty('--mdp-icon-rotation-open', open.trim());
      } else {
        summary.style.setProperty('--mdp-icon-rotation', '0deg');
        summary.style.setProperty('--mdp-icon-rotation-open', rotation);
      }
    }

    return summary;
  } // createSummary


  /**
   * Logic to determine if a form element should trigger an "open" state
   */
  static isTriggered(el) {
    const tag = el.tagName;
    if (tag === 'INPUT') {
      if (el.type === 'checkbox' || el.type === 'radio') return el.checked;
      return el.value.trim() !== "";
    }
    if (tag === 'SELECT') {
      return el.multiple
        ? Array.from(el.selectedOptions).some(o => o.value !== "")
        : el.value !== "";
    }
    if (tag === 'TEXTAREA') return el.value.trim() !== "";
    return false;
  } // isTriggered


  /**
   * Syncs the <details> state with its controller(s)
   */
  static updateDetailsState(details, targetId, forceState = null) {
    if (!details) return;
    const controllers = document.querySelectorAll(`[data-controls-details="${targetId}"]`);
    if (!controllers.length) return;

    const shouldBeOpen = forceState !== null
      ? forceState
      : Array.from(controllers).some(ctrl => this.isTriggered(ctrl));

    if (details.open !== shouldBeOpen) {
      details.open = shouldBeOpen;

      // Toggle 'required' for hidden/visible fields
      details.querySelectorAll('[data-required]').forEach(f => f.required = shouldBeOpen);

      // Update ARIA
      controllers.forEach(el => {
        if (!['INPUT', 'SELECT', 'TEXTAREA'].includes(el.tagName)) {
          el.setAttribute('aria-expanded', shouldBeOpen);
        }
      });
    }
  } // updateDetailsState


  /**
   * Static method to handle global event delegation
   */
  static attachGlobalListeners() {
    if (this.listenersAttached) return;

    // Handle Input/Change
    ['change', 'input'].forEach(eventType => {
      document.addEventListener(eventType, (e) => {
        const controller = e.target;

        // Radio Group logic
        if (controller.type === 'radio' && controller.name) {
          const group = document.querySelectorAll(`input[type="radio"][name="${controller.name}"]`);
          group.forEach(radio => {
            const rId = radio.dataset.controlsDetails;
            if (rId) this.updateDetailsState(document.getElementById(rId), rId);
          });
          return;
        }

        const targetId = controller.dataset.controlsDetails;
        if (targetId) this.updateDetailsState(document.getElementById(targetId), targetId);
      });
    });

    // Handle Clicks
    document.addEventListener('click', (e) => {
      const ctrl = e.target.closest('[data-controls-details]');
      if (!ctrl || ['INPUT', 'SELECT', 'TEXTAREA'].includes(ctrl.tagName)) return;

      if (ctrl.tagName === 'A') e.preventDefault();

      const targetId = ctrl.dataset.controlsDetails;
      const details = document.getElementById(targetId);
      const action = ctrl.dataset.action;

      let newState = !details.open;
      if (action === 'open') newState = true;
      if (action === 'close') newState = false;

      this.updateDetailsState(details, targetId, newState);
    });

    this.listenersAttached = true;
  } // attachGlobalListeners

} // RevealAccordion
