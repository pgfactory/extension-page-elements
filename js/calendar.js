/*
**  calendar.js
 */
/*
 * Selectors:
day view:
  parent: .fc-view-harness
    week-view:  fc-timeGridWeek-view fc-view fc-timegrid
    month-view: fc-dayGridMonth-view fc-view fc-daygrid
    list-view:  fc-listYear-view fc-view fc-list fc-list-sticky

  free cell:
    parent: .fc-timegrid-body
      day-col: .fc-day
      time-row: .fc-timegrid-slot  <- trigger

*/

console.log('calendar.js');

const pfyCalContextMenu =
  `<button class="pfy-cal-edit" role="button">{{ pfy-cal-context-edit-label }}</button><br>`+
  `<button class="pfy-cal-duplicate" role="button">{{ pfy-cal-context-duplicate-label }}</button><br>`+
  `<button class="pfy-cal-delete" role="button">{{ pfy-cal-context-delete-label }}</button>`;

function PfyCalendar() {
  this.fullCal = null;
  this.calendarEl = null;
  this.options = null;
  this.formWrapperEl = document.querySelector('.pfy-cal-form-wrapper');
  this.form = this.formWrapperEl.querySelector('form');
  this.titleWidth = null;
  this.freezePast = null;
  this.tippyInstance = false;
  this.clicks = 0;
  this.calEvs = {};
  this.fullCalendarOptions = null;
  this.useDblClick = false;
  this.overrideDblClick = false;

  // Control array: list-view <tr> rows with any of these classes will be removed
  this.listRowRemoveClasses = ['pfy-event-abwesenheit'];

  return this;
} // PfyCalendar


PfyCalendar.prototype.init = function (calendarEl, options) {
  const parent = this;
  let   dataRef = '';
  this.calendarEl = calendarEl;

  this.initCatSelectrHandler();
  this.setupContextMenu();

  domForOne(this.formWrapperEl, '[name=_dataSrcInx]', (dataRefElem) => {
    dataRef = dataRefElem.value;
  });
  if (!dataRef) {
    alert('Error: dataSrcInx missing');
  }

  this.ajaxUrl = pageUrl + '?ajax&calendar&datasrcinx=' + dataRef;

  this.options = options;
  this.editPermission = this.options.edit || this.options.admin;
  this.freezePast = options.freezePast;
  this.fullCalendarOptions = options.fullCalendarOptions;
  this.useDblClick = (typeof this.options.useDblClick !== 'undefined') && this.options.useDblClick;

  // Setting default values for 'calDayStart' property
  if (typeof options.fullCalendarOptions.slotMinTime !== 'undefined') {
    this.options.calDayStart = options.fullCalendarOptions.slotMinTime;
  } else {
    this.options.calDayStart = '08:00';
  }

  const businessHoursFrom = this.options.businessHours.substring(0,5);
  const businessHoursTill = this.options.businessHours.substring(6);
  const visibleHoursFrom = this.options.visibleHours.substring(0,5);
  const visibleHoursTill = this.options.visibleHours.substring(6);

  const editPermission = this.editPermission && this.options.draggable;

  // Default values for FullCalendar options
  let fullCalendarOptionDefaults = {
        headerToolbar: {
            left:     this.options.headerLeftButtons,
            center:   'title',
            right:    this.options.headerRightButtons,
        },
        nowIndicator:           true,
        editable:               editPermission,
        eventStartEditable:     editPermission,
        eventDurationEditable:  editPermission,
        navLinks:               true, // can click day/week names to navigate views
        height:                 'auto',
        dayMaxEvents:           true, // allow "more" link when too many events
        //selectable: true,
        weekNumbers:            true,
        weekNumberCalculation:  'ISO',
        slotMinTime:            visibleHoursFrom,
        slotMaxTime:            visibleHoursTill,
        businessHours:          { daysOfWeek: [ 1, 2, 3, 4, 5 ], startTime: businessHoursFrom, endTime: businessHoursTill},
        buttonText: {
            prev:     `{{ pfy-cal-label-prev }}`,
            next:     `{{ pfy-cal-label-next }}`,
            prevYear: `{{ pfy-cal-label-prev-year }}`,
            nextYear: `{{ pfy-cal-label-next-year }}`,
            year:     `{{ pfy-cal-label-year }}`,
            month:    `{{ pfy-cal-label-month }}`,
            week:     `{{ pfy-cal-label-week }}`,
            day:      `{{ pfy-cal-label-day }}`,
            today:    `{{ pfy-cal-label-today }}`,
            list:     `{{ pfy-cal-label-list }}`,
        },
        weekText:     `{{ pfy-cal-label-week-nr }}`, /* e.g. "KW" */
        allDayText:   `{{ pfy-cal-label-allday }}`,
        moreLinkText: `{{ pfy-cal-label-more-link }}`,
        noEventsText: `{{ pfy-cal-label-empty-list }}`,

        // get cal-events:
        events: {
            url: this.ajaxUrl + '&get',
            success: function( data ) {
              if (typeof data === 'object') {
                console.log('cal events fetched: ' + data.length);
                parent.removeHiddenElements(data);
              } else {
                console.log(data);
              }
            },
            failure: function( events ) {
                console.log('Error in calendar data:');
                console.log( events );
            }
        },

        // render events one by one:
        eventContent: function( calArgs ) {
          return parent.renderEvent( calArgs );
        },
        viewDidMount: function (calEv) {
            parent.onViewReady( calEv );
        },
        dateClick: function (dateObj) {
            parent.openNewEventPopup(dateObj);
        },
        eventClick: function (calObj) {
            parent.openExistingEventPopup(calObj);
        },
         eventDrop: function (calEv) {
             return parent.calEventChanged(calEv);
         },
         eventResize: function (calEv) {
             parent.calEventChanged(calEv);
         },
         windowResize: function() {
            parent.handleWindowWidth();
         },
         datesSet(events) {
            // events are loaded but not placed into the view
            setTimeout(() => {
              parent.onCalendarReady(events);
            }, 300);
         },
  };

  // Merging default options with provided options
  let fullCalendarOptions = Object.assign({}, fullCalendarOptionDefaults, options.fullCalendarOptions);

  // Creating a FullCalendar instance
  calendarEl.innerHTML = '';
  this.fullCal = new FullCalendar.Calendar(calendarEl, fullCalendarOptions);
  this.fullCal.render();

  this.handleWindowWidth();
  this.setupSwipe();
}; // init


PfyCalendar.prototype.handleWindowWidth = function() {
  if (this.titleWidth === window.innerWidth) {
    return;
  }
  const calendarEl = this.calendarEl;
  this.titleWidth = calendarEl.offsetWidth;
  let headerWidth = 0;
  domForEach(calendarEl, '.fc-header-toolbar > div', (elem) => {
    headerWidth += elem.offsetWidth;
  });

  console.log(`headerWidth: ${headerWidth} titleWidth: ${this.titleWidth}`);
  if (headerWidth > this.titleWidth) {
    calendarEl.classList.add('pfy-calendar-narrow');
  } else {
    calendarEl.classList.remove('pfy-calendar-narrow');
  }
}; // handleWindowWidth


PfyCalendar.prototype.renderEvent = function( calArgs ) {
  const event = calArgs.event;
  const id = event._def.defId;
  this.calEvs[id] = event;
  let html = event._def.extendedProps.summary;
  if (typeof event._def.extendedProps.description !== 'undefined') {
    html += event._def.extendedProps.description;
  }
  html = html.replace(/^<span/, `<span data-event-id='${id}'`);
//  this.updateSelectedCategories();
  return { html: html };
}; // renderEvent


PfyCalendar.prototype.calEventChanged = async function(event0) {
  if (!this.editPermission) {
    return false;
  }

  let event = event0.event;

  const msg = this.checkPermission(event);
  if (msg) {
    this.showAlertPopup(msg);
    return;
  }

  if (!this.checkFreeze(event)) {
    this.fullCal.refetchEvents();
    this.showAlertPopup(`{{ pfy-cal-no-permission-event-in-past }}`);
    return;
  }
  const start = event._instance.range.start.toISOString().substring(0,16);
  const end = event._instance.range.end.toISOString().substring(0,16);
  this.modifyEvent(event0, start, end);
}; // calEventChanged


PfyCalendar.prototype.onCalendarReady = function() {
  const parent = this;
  domForEach('.fc-view', (calendarEl) => {
    domForEach(calendarEl, '.fc-event', (calEventEl) => {

      // for each event in calendar, copy cat-class to event elem:
      domForOne(calEventEl, '[data-reckey]', (el) => {
        calEventEl.classList.value += ' ' + el.classList.value;
      })

      // handle description:
      domForOne(calEventEl, '.pfy-cal-description', (descr) => {
        if (descr.innerHTML.trim()) {
          tippy(calEventEl, {
            content: descr.innerHTML,
            trigger: parent.useDblClick ? 'click' : 'mouseenter focus',
            allowHTML: true,
            theme: 'light',
            delay: [0, 200],
          });
        }
      })
    })
  });
}; // onCalendarReady


PfyCalendar.prototype.onViewReady = function( calendarObj ) {
  // view changed, so update state on host:
  let viewType = calendarObj.view.type;
  if (this.options.initialView !== viewType) {
    this.storeViewMode(viewType);
    this.options.initialView = viewType;
  }
}; // onViewReady


PfyCalendar.prototype.storeViewMode = function(viewName) {
  execAjaxPromise('&mode=' + viewName, null, this.ajaxUrl)
    .then(function (data) {
      console.log('storeViewMode done: ' + data);
    });
}; // storeViewMode


PfyCalendar.prototype.storeCatFilter = function() {
  let catfilter = this.getCatFilterStates();
  catfilter = catfilter.join(',');
  execAjaxPromise(`&mode=${catfilter}&catfilter`, null, this.ajaxUrl)
    .then(function (data) {
      console.log('storeViewMode done: ' + data);
    });
}; // storeCatFilter


PfyCalendar.prototype.openNewEventPopup = function (dateObj) {
  const parent = this;
  this.invokeHandler(this._openNewEventPopup, dateObj, null);
}; // openNewEventPopup


PfyCalendar.prototype._openNewEventPopup = function (parent, dateObj) {
  if (!parent.performChecks(false)) {
    return;
  }

  let form = null;
  let isAllday = false;

  parent.openPopup(`{{ pfy-cal-new-entry }}`);
  form = document.querySelector('.pfy-popup-container .pfy-form');

  pfyFormsHelper.initRepetitionWidget(form);

  const data = {
    start: dateObj.dateStr,
  };
  const del = form.querySelector('div.pfy-cal-delete-entry');
  del.classList.add('pfy-dispno');

  domForOne(form, 'input[name=end]', (endElem) => {
    const defaultDuration = endElem.dataset.eventDuration;
    if (defaultDuration === 'allday') {
      isAllday = true;
      endElem.dataset.eventDuration = 1440;
      data.start = dateObj.dateStr.substring(0,10);
      data.end = data.start;
    }
  })

  if (!isAllday) {
    if (dateObj.view.type === 'dayGridMonth') {
      isAllday = true;
    } else if (data.start.length < 16) {
      isAllday = true;
    }
  }

  parent.setAlldayMode(form, isAllday);

  pfyFormsHelper.presetForm(form, data, '');

  domForOne('.pfy-popup-wrapper', (popup) => {
    popup.style.opacity = 1;
  })

  parent.setupTriggers(form);
}; // _openNewEventPopup


PfyCalendar.prototype.openExistingEventPopup = function (calObj) {
  this.invokeHandler(this._openExistingEventPopup, calObj, calObj.el);
}; // openCalPopup


PfyCalendar.prototype._openExistingEventPopup = function (parent, calObj, calEl) {
  const calEv = calObj.event;
  if (!parent.performChecks(calEv)) {
    return;
  }

  parent.openPopup(`{{ pfy-cal-modif-entry }}`);
  const form = document.querySelector('.pfy-popup-container .pfy-form');
  pfyFormsHelper.initRepetitionWidget(form);
  let recKey = '';
  if (calEl) {
    const el = calEl.querySelector('[data-reckey]');
    recKey = el.dataset.reckey;
  }
  parent.presetForm(form, recKey);
  parent.setupTriggers(form);
}; // _openExistingEventPopup


PfyCalendar.prototype.setupTriggers = function (form) {
  this.setupCategoryHandler(form);
  this.setupCancelHandler(form);
  this.setupDeleteCheckboxHandler(form);
  this.setupAllDayHandler(form);
}; // setupTriggers


PfyCalendar.prototype.setupAllDayHandler = function(form) {
  // All-day toggle:
  const parent = this;
  domForOne(form, '[name=allday]', (alldayCheckbox) => {
    alldayCheckbox.addEventListener('change', function () {
      const allday = alldayCheckbox.checked;
      parent.setAlldayMode(form, allday);
    });
  })
}; // setupAllDayHandler


PfyCalendar.prototype.setAlldayMode = function(form, allday) {
//  // if month view, default to allday, unless there is a preset value:
//  domForOne(form, '.pfy-elem-wrapper.pfy-cal-allday', el => {
//    const presetVal = el.dataset.preset;
//    console.log('preset: ' + presetVal);
//    if (typeof presetVal !== 'undefined') {
//      if (presetVal === 'false' || !presetVal) {
//        allday = false;
//      }
//    }
//  })

  if (allday) {
    console.log('is allday');
    domForOne(form, '[name=start]', (startElem) => {
      const startVal = startElem.value;
      startElem.setAttribute('type', 'date');
      startElem.dataset.orig = startVal;
      startElem.value = startVal.substring(0,10);
    })
    domForOne(form, '[name=end]', (endElem) => {
      const endVal = endElem.value;
      endElem.setAttribute('type', 'date');
      endElem.dataset.orig = endVal;
      endElem.value = endVal.substring(0,10);
    })
  } else {
    console.log('not allday');
    domForOne(form, '[name=start]', (startElem) => {
      let startVal = startElem.dataset.orig || startElem.value;
      startElem.setAttribute('type', 'datetime-local');
      if (startVal && startVal.length < 16) {
        startVal += 'T12:00';
      }
      startElem.value = startVal;
    })
    domForOne(form, '[name=end]', (endElem) => {
      let endVal = endElem.dataset.orig || endElem.value;
      endElem.setAttribute('type', 'datetime-local');
      if (endVal && endVal.length < 16) {

        endVal += 'T13:00';
      }
      endElem.value = endVal;
    })
  }
  domForOne(form, '[name=allday]', (el) => {
    el.closest('.pfy-elem-wrapper').dataset.value = allday;
  })
} // setAlldayMode


PfyCalendar.prototype.setupDeleteCheckboxHandler = function(form) {
  // Delete Entry checkbox:
  domForOne(form, 'input.pfy-cal-delete-entry', (deleteCheckbox) => {
    deleteCheckbox.addEventListener('change', function (ev) {
      ev.preventDefault();
      const submitBtn = form.querySelector('input.pfy-submit');
      if (deleteCheckbox.checked) {
        submitBtn.value = `{{ pfy-cal-delete-entry-now }}`;
      } else {
        submitBtn.value = `{{ pfy-cal-submit-entry-now }}`;
      }
    });
  })
}; // setupDeleteCheckboxHandler


PfyCalendar.prototype.setupCategoryHandler = function(form) {
  const parent = this;
  domForOne(form, 'select[name=category]', (categoryElem) => {
    const wrapper = form.closest('.pfy-popup-container');
    if (wrapper) {
      wrapper.classList.remove('pfy-scroll-hints');
    }
    categoryElem.addEventListener('change', function (event) {
      parent.updateCategoryClass(event.currentTarget);
    });
  })
}; // setupCategoryHandler


PfyCalendar.prototype.updateCategoryClass = function(catElem) {
  const currentCat =  catElem.options[catElem.selectedIndex].value;
  const wrapper = catElem.closest('.pfy-popup-wrapper');
  let classes = wrapper.classList.value;
  classes = classes.replace(/\s*pfy-category-\w*/, '');
  classes += ' pfy-category-'+currentCat;
  wrapper.classList.value = classes;
}; // updateCategoryClass


PfyCalendar.prototype.setupCancelHandler = function(form) {
  const parent = this;
  domForOne(form, 'input.pfy-cancel.button', (cancelBtn) => {
    cancelBtn.addEventListener('click', function (event) {
      event.preventDefault();
      parent.closePopup();
    });
  })
}; // setupCancelHandler


PfyCalendar.prototype.launchWindowFreeze = function() {
  pfyFormsHelper.freezeWindowAfter('1 hour', `{{ pfy-cal-timeout-alert }}`);
}; // launchWindowFreeze


PfyCalendar.prototype.checkPermission = function(event) {
  let modifyPermission = this.options.modifyPermission;
  if (modifyPermission) {
    try {
      modifyPermission = ',' + modifyPermission.replace(' ', '') + ',';
      let creator = event._def.extendedProps._creator;
      if (!modifyPermission.includes(',' + creator + ',')) {
        console.log(`Attempt to modify event created by other user "${creator}" -> blocked.`);
        this.fullCal.refetchEvents();
        return `{{ pfy-cal-no-permission-others-event }}`;
      }
    } catch(e) {}
  }

  let calCatPermission = this.options.calCatPermission;
  if (calCatPermission) {
    try {
      let category = event._def.extendedProps.category.toLowerCase();
      if (calCatPermission.toLowerCase().indexOf(category) === -1) {
        console.log(`Attempt to modify event of unauthorized category ${category} -> blocked.`);
        this.fullCal.refetchEvents();
        return `{{ pfy-cal-no-permission-others-category }}`;
      }
    } catch(e) {}
  }
  return false;
}; // checkPermission


PfyCalendar.prototype.checkFreeze = function(calEv, checkAgainstEnd = false) {
  if (this.options.admin || !this.freezePast) {
    return true;
  }
  const now = new Date().toLocaleString('sv', { timeZone: this.fullCalendarOptions.timeZone }).replace(' ', 'T');
  let  d = null;
  if (typeof calEv.dateStr !== 'undefined') {
    d = calEv.dateStr;

  } else if (typeof calEv._instance.range !== 'undefined') {
    if (checkAgainstEnd) {
      d = calEv._instance.range.end.toISOString();
    } else {
      d = calEv._instance.range.start.toISOString();
    }
  } else {
    console.log("ERROR");
  }
  console.log(`now: ${now}  then: ${d}`);
  return now < d;
}; // checkFreeze


PfyCalendar.prototype.openPopup = function(header) {
  pfyPopup({
    contentFrom: '.pfy-cal-form-wrapper .pfy-form-wrapper',
    header: header,
    draggable: true,
    closeOnBgClick: false,
    initialOpacity: 0.1,
  });

  // modify all ids within popup:
  domForEach('.pfy-popup-wrapper [id]', (id) => {
    const idAttr = 'pfy-inpopup-' + id.getAttribute('id');
    id.setAttribute('id', idAttr);
  })
  // modify all for within popup:
  domForEach('.pfy-popup-wrapper [for]', (forAttr) => {
    const idAttr = 'pfy-inpopup-' + forAttr.getAttribute('for');
    forAttr.setAttribute('for', idAttr);
  })
}; // openPopup


PfyCalendar.prototype.closePopup = function() {
  pfyPopupClose(this.formWrapperEl);
}; // closePopup


PfyCalendar.prototype.presetForm = function(form1, recKey) {
  if (typeof recKey === 'undefined') {
    return;
  }
  const parent = this;
  let cmd = 'getRec='+recKey;
  console.log('fetching data record '+recKey);
  execAjaxPromise(cmd, {}, this.ajaxUrl)
    .then(function (data) {
      const form = document.querySelector('.pfy-popup-container .pfy-form');
      if (data.allday) {
        console.log('allday');
        parent.setAlldayMode(form, true);
      }
      pfyFormsHelper.presetForm(form, data, recKey);
      const categoryElem = form.querySelector('select[name=category]');
      if (categoryElem) {
        parent.updateCategoryClass(categoryElem);
      }

      const popup = document.querySelector('.pfy-popup-wrapper');
      popup.style.opacity = 1;

    })
    .then(function (msg) {
      if (msg) {
        console.log(msg);
      }
    });
}; // resetForm


PfyCalendar.prototype.modifyEvent = function(event0, start, end) {
  const parent = this;
  const fullCal = this.fullCal;
  const eventEl = event0.el;
  const span = eventEl.querySelector('.fc-event [data-reckey]');
  const recKey = span.dataset.reckey;
  if (typeof recKey === 'undefined') {
    return;
  }

  if (event0.event._def.allDay) {
    start = start.substring(0,10);
    end = end.substring(0,10);
  }

  let cmd = 'modifyRec='+recKey;
  cmd += `&start=${start}&end=${end}&calendar`;
  console.log('modifying data record '+recKey);
  execAjaxPromise(cmd, {}, this.ajaxUrl)
    .then(function (data) {
      // update calendar:
      fullCal.refetchEvents();
    })
    .then(function (msg) {
      console.log(msg);
    });
}; // modifyEvent


PfyCalendar.prototype.showAlertPopup = function (str) {
  pfyAlert({content: str});
}; // showAlertPopup


PfyCalendar.prototype.setupSwipe = function() {
  const parent = this;
  swipedetect(this.calendarEl, function(swipedir){
    if (swipedir === 'left') {
      console.log('swiped left');
      parent.fullCal.next();
    } else if (swipedir === 'right') {
      console.log('swiped right');
      parent.fullCal.prev();
    }
  });
}; // setupSwipe



PfyCalendar.prototype.setupContextMenu = function() {
console.log('setupContextMenu');
  const parent = this;
  document.addEventListener('contextmenu', (ev) => {
    if (!ev.target.closest('.fc-event')) {
      return;
    }
    parent.openContextMenu(ev);
  })

  document.addEventListener('click', (ev) => {
    if (!ev.target.closest('.tippy-content')) {
      return;
    }
    parent.handleContextMenu(ev);
  })
} // setupContextMenu


PfyCalendar.prototype.openContextMenu = function(ev) {
  const parent = this;
  ev.preventDefault();
  const calEventEl = ev.target.closest('.fc-event');

  const parentEl = calEventEl.parentElement.parentElement;
  let tippyInstance = parent.tippyInstance = tippy(calEventEl, {
    content: pfyCalContextMenu,
    placement: 'right-end',
    trigger: 'manual',
    interactive: true,
    arrow: false,
    allowHTML: true,
    hideOnClick: true,
    theme: 'light', //???
    offset: [0, 10],
  });
  tippyInstance.show();
} // openContextMenu


PfyCalendar.prototype.handleContextMenu = function(ev) {
  const parent = this;
  ev.preventDefault();
  const el = ev.target;
  if (el.closest('button.pfy-cal-delete')) {
    parent.handleContextMenuDelete(ev);

  } else if (el.closest('button.pfy-cal-duplicate')) {
    parent.handleContextMenuDuplicate(ev);

  } else if (el.closest('button.pfy-cal-edit')) {
    parent.handleContextMenuEdit(ev);
  }
} // handleContextMenu


PfyCalendar.prototype.handleContextMenuDelete = function(ev) {
  const parent = this;
  ev.stopPropagation();
  ev.stopImmediatePropagation();
  ev.preventDefault();
  domForOne(this.tippyInstance.reference.parentElement, '[data-reckey]', (el) => {
    const recKey = el.dataset.reckey;
    execAjaxPromise('&delete=' + recKey, null, parent.ajaxUrl)
      .then(function (data) {
        parent.fullCal.refetchEvents();
      });
  });
} // handleContextMenuDelete


PfyCalendar.prototype.handleContextMenuDuplicate = function(ev) {
  const parent = this;
  console.log(ev.target);
  console.log('duplicate');
  ev.stopPropagation();
  ev.stopImmediatePropagation();
  ev.preventDefault();
  domForOne(this.tippyInstance.reference.parentElement, '[data-reckey]', (el) => {
    const recKey = el.dataset.reckey;
    execAjaxPromise('&duplicate=' + recKey, null, parent.ajaxUrl)
      .then(function (data) {
        parent.fullCal.refetchEvents();
      });
  });
} // handleContextMenuDuplicate


PfyCalendar.prototype.handleContextMenuEdit = function(ev) {
  const parent = this;
  console.log(ev.target);
  console.log('edit');
  const calEventEl = this.tippyInstance.reference;
  parent.closeContextMenu();
  this.overrideDblClick = true;
  // emulate click on cal event to open edit popup:
  calEventEl.click();
} // handleContextMenuEdit


PfyCalendar.prototype.closeContextMenu = function() {
  if (this.tippyInstance) {
    this.tippyInstance.destroy(); //ToDo: why not working?
    this.tippyInstance = false;
  }
} // closeContextMenu


PfyCalendar.prototype.performChecks = function(calEv = false) {
  if (!this.editPermission) {
    console.log('User has insufficient privileges to edit calendar');
    if (this.editPermission === null) {
      this.pfyPopup(`{{ pfy-warning-insufficient-privileges }}`);
    }
    return false;
  }

  if (calEv) {
    const msg = this.checkPermission(calEv);
    if (msg) {
      this.showAlertPopup(msg);
      return false;
    }

    if (!this.checkFreeze(calEv, true)) {
      this.showAlertPopup(`{{ pfy-cal-no-permission-event-in-past }}`);
      return false;
    }
  }
  return true;
} // performChecks


PfyCalendar.prototype.invokeHandler = function(fun, argObj1, argObj2 = null) {
  if (this.tippyInstance) {
    this.closeContextMenu();
    return;
  }

  const parent = this;
  if (this.useDblClick && !this.overrideDblClick) {
    this.clicks++;
    if (this.clicks === 1) {
      setTimeout(function () {
        if (parent.clicks === 1) {
          //console.log('ignoring single click');
        } else {
          fun(parent, argObj1, argObj2);
        }
        parent.clicks = 0;
      }, 500);
    }
  } else {
    fun(parent, argObj1, argObj2);
  }
  this.overrideDblClick = false
} // invokeHandler


PfyCalendar.prototype.initCatSelectrHandler = function() {
  const parent = this;
  console.log('initCatSelectrHandler');
  domForEach('.pfy-cal-cat-selector input', (el) => {
    el.addEventListener('change', function () {
      parent.fullCal.refetchEvents();
      parent.storeCatFilter();
    });
  })
} // closeContextMenu


PfyCalendar.prototype.getCatFilterStates = function() {
  let filteredClasses = [];
  domForEach('.pfy-cal-cat-selector input', (el) => {
    if (!el.checked) {
      filteredClasses.push(el.value);
    }
  })
  return filteredClasses;
}

PfyCalendar.prototype.removeHiddenElements = function(data) {
  if (typeof data === 'undefined') {
    return data;
  }

  const listView = (this.fullCal.view.type === 'listYear');
  let filteredClasses = this.getCatFilterStates();
  if (!filteredClasses && !listView) {
    return data;
  }
  filteredClasses.forEach((cls) => {
    let i = 0;
    while (typeof data[i] !== 'undefined') {
      if (data[i].summary.match(cls)) {
        this.removeAndShift(data, i);
      } else {
        i++;
      }
    }
  })

  if (listView && this.options.hideAllDayInListView) {
    let i = 0;
    while (typeof data[i] !== 'undefined') {
      if (data[i].start.length <= 12) {
        //console.log('removing ', data[i]);
        this.removeAndShift(data, i);
      }
      i++;
    }
  }
} // removeHiddenElements


PfyCalendar.prototype.removeAndShift = (obj, keyToRemove) => {
  const target = parseInt(keyToRemove);

  // 1. Delete the target property
  delete obj[target];

  // 2. Find all keys, sort them, and shift higher ones down
  Object.keys(obj)
    .map(Number)
    .filter(key => key > target)
    .sort((a, b) => a - b) // Sort ascending to move them in order
    .forEach(key => {
      obj[key - 1] = obj[key];
      delete obj[key];
    });
  obj.length--;
} // removeAndShift

