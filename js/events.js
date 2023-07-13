/*
 *  events.js
 */

"use strict";

// const pfyEventsHelper = {

const pfyEventsHelper = {

  init() {
    pfyEventsHelper.setupTriggers();
  }, // init


  setupTriggers() {
    const catSelect = document.querySelector('#frm-category');
    if (catSelect) {
      catSelect.addEventListener('change', function (e) {
        mylog(e);
      });
    }
  }, // setupTriggers

}; // pfyFormsHelper

pfyEventsHelper.init();
