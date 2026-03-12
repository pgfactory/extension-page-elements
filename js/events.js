/*
 *  events.js
 */

"use strict";


const pfyEventsHelper = {

  init() {
    this.setupTriggers();
  }, // init


  setupTriggers() {
    const catSelect = document.querySelector('#frm-category');
    if (catSelect) {
      catSelect.addEventListener('change', function (e) {
        // todo: implement category change handler
      });
    }
  }, // setupTriggers

}; // pfyEventsHelper

pfyEventsHelper.init();
