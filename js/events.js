/*
 *  events.js
 */

"use strict";


const pfyEventsHelper = {

  init() {
    pfyEventsHelper.setupTriggers();
  }, // init


  setupTriggers() {
    const catSelect = document.querySelector('#frm-category');
    if (catSelect) {
      catSelect.addEventListener('change', function (e) {
        console.log(e);
      });
    }
  }, // setupTriggers

}; // pfyFormsHelper

pfyEventsHelper.init();
