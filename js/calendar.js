/*
**  calendar.js
 */



var inx = 0;

function PfyCalendar() {
  this.fullCal = null;
  this.options = null;
  this.inx = null;
  this.$formWrapper = document.querySelector('.pfy-cal-form-wrapper');;
  this.form = this.$formWrapper.querySelector('form');
  this.titleWidth = null;
  this.freezePast = null;
  return this;
} // PfyCalendar


PfyCalendar.prototype.init = function ($elem, options) {
  const inx = options.inx;
  const parent = this;
  let   dataRef = '';
  this.$elem = $elem;

  const dataRefElem = this.$formWrapper.querySelector('[name=_formInx]');
  if (dataRefElem) {
    dataRef = dataRefElem.value;
  }
  this.ajaxUrl = pageUrl + '?ajax&calendar&datasrcinx=' + dataRef;

  this.options = options;
  this.editPermission = this.options.edit || this.options.admin;
  this.freezePast = options.freezePast;

  this.inx = inx;

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

  // Default values for FullCalendar options
  let fullCalendarOptionDefaults = {
        headerToolbar: {
            left: this.options.headerLeftButtons,
            center: 'title',
            right: this.options.headerRightButtons,
        },
        nowIndicator:       true,
        editable: this.editPermission,
        eventStartEditable: this.editPermission,
        eventDurationEditable: this.editPermission,
        navLinks: true, // can click day/week names to navigate views
        height: 'auto',
        dayMaxEvents: true, // allow "more" link when too many events
        //selectable: true,
        weekNumbers: true,
        weekNumberCalculation: 'ISO',
        slotMinTime: visibleHoursFrom,
        slotMaxTime: visibleHoursTill,
        businessHours: { daysOfWeek: [ 1, 2, 3, 4, 5 ], startTime: businessHoursFrom, endTime: businessHoursTill},
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
                mylog('cal events fetched: ' + data.length);
                setTimeout(parent.onCalendarReady, 1);
              } else {
                mylog(data);
              }
            },
            failure: function( events ) {
                console.log('Error in calendar data:');
                console.log( events );
            }
        },
            // render events one by one:
            eventContent: function( args ) {
                return parent.renderEvent( args );
            },
            viewDidMount: function (e) {
                parent.onViewReady( e );
            },
            dateClick: function (e) {
                parent.openCalPopup(inx, e, true);
            },
            eventClick: function (e) {
                parent.openCalPopup(inx, e, false);
            },
             eventDrop: function (e) {
                 return parent.calEventChanged(inx, e);
             },
             eventResize: function (e) {
                 parent.calEventChanged(inx, e);
             },
            windowResize: function() {
                parent.handleWindowWidth();
            }
  };

  // Merging default options with provided options
  let fullCalendarOptions = Object.assign({}, fullCalendarOptionDefaults, options.fullCalendarOptions);

  // Creating a FullCalendar instance
  $elem.innerHTML = '';
  this.fullCal = new FullCalendar.Calendar($elem, fullCalendarOptions);
  this.fullCal.render();

  this.handleWindowWidth();
  this.setupSwipe();
}; // init


PfyCalendar.prototype.handleWindowWidth = function() {
  if (this.titleWidth === window.innerWidth) {
    return;
  }
  const $elem = this.$elem;
  this.titleWidth = $elem.offsetWidth;
  const headerElem = $elem.querySelector('.fc-header-toolbar');
  const headerElements = headerElem.querySelectorAll(':scope > div');
  let headerWidth = 0;
  headerElements.forEach(function (elem) {
    headerWidth += elem.offsetWidth;
  });
  mylog(`headerWidth: ${headerWidth} titleWidth: ${this.titleWidth}`);
  if (headerWidth > this.titleWidth) {
    $elem.classList.add('pfy-calendar-narrow');
  } else {
    $elem.classList.remove('pfy-calendar-narrow');
  }
}; // handleWindowWidth


PfyCalendar.prototype.renderEvent = function( arg ) {
  return { html: arg.event._def.title };
}; // renderEvent


PfyCalendar.prototype.calEventChanged = async function(inx, event0) {
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
  const calendars = document.querySelectorAll('.fc-view');
  if (calendars) {
    calendars.forEach(function (calendar) {
      const events = calendar.querySelectorAll('.fc-event');
      if (events) {
        events.forEach(function (event) {
          const el = event.querySelector('.fc-event-main > span');
          if (el) {
            const cls = el.classList.value;
            event.classList.add(cls);
          }
          // init tooltip:
          const descr = event.querySelector('.description');
          if (descr && descr.innerHTML) {
            tippy(event, {
              content: descr.innerHTML,
              allowHTML: true,
              theme: 'light',
              delay: [0, 200],
            });
          }
        });
      }
    });
  }
}; // onCalendarReady


PfyCalendar.prototype.onViewReady = function( arg ) {
  // view changed, so update state on host:
  let viewType = arg.view.type;
  if (this.options.initialView !== viewType) {
    this.storeViewMode(inx, viewType);
    this.options.initialView = viewType;
  }
}; // onViewReady


PfyCalendar.prototype.storeViewMode = function( inx, viewName ) {
  execAjaxPromise('&mode=' + viewName, null, this.ajaxUrl)
    .then(function (data) {
      mylog('storeViewMode done: ' + data);
    });
}; // storeViewMode


PfyCalendar.prototype.openCalPopup = function (inx, event0, isNewEvent) {
  if (!this.editPermission) {
    mylog('User has insufficient privileges to edit calendar');
    if (this.editPermission === null) {
      this.pfyPopup('{{ pfy-warning-insufficient-privileges }}');
    }
    return;
  }

  let event = event0.event;

  const msg = this.checkPermission(event);
  if (msg) {
    this.showAlertPopup(msg);
    return;
  }

  if (!this.checkFreeze(event0, true)) {
    this.showAlertPopup(`{{ pfy-cal-no-permission-event-in-past }}`);
    return;
  }


  let form = null;
  let isAllday = false;
  if (isNewEvent) {
    this.openPopup(`{{ pfy-cal-new-entry }}`);
    form = document.querySelector('.pfy-popup-container .pfy-form');
    const data = {
      start: event0.dateStr,
    };
    const del = form.querySelector('div.pfy-cal-delete-entry');
    del.classList.add('pfy-dispno');

    const endElem = form.querySelector('input[name=end]');
    if (endElem) {
      const defaultDuration = endElem.dataset.eventDuration;
      if (defaultDuration === 'allday') {
        isAllday = true;
        endElem.dataset.eventDuration = 1440;
        data.start = event0.dateStr.substring(0,10);
        data.end = data.start;
      }
    }

    if (!isAllday && event0.view.type === 'dayGridMonth') {
      data.start += 'T12:00';
      this.setAlldayMode(form, false);
    }

    if (!isAllday && data.start.length < 16) {
      isAllday = true;
      this.setAlldayMode(form, true);
    }

    pfyFormsHelper.presetForm(form, data, '');

    if (isAllday) {
      const alldayElem = form.querySelector('[name=allday]');
      alldayElem.checked = true;
    }

    const popup = document.querySelector('.pfy-popup-wrapper');
    popup.style.opacity = 1;

  } else { // existing event
    this.openPopup(`{{ pfy-cal-modif-entry }}`);
    form = document.querySelector('.pfy-popup-container .pfy-form');
    let recKey = '';
    const eventEl = event0.el;
    if (eventEl) {
      const span = eventEl.querySelector('.fc-event-main > span');
      recKey = span.dataset.reckey;
    }
    this.presetForm(form, recKey);
  }
  this.setupTriggers(form);
}; // openCalPopup


PfyCalendar.prototype.setupTriggers = function (form) {
  this.setupCategoryHandler(form);
  this.setupCancelHandler(form);
  this.setupDeleteCheckboxHandler(form);
  this.setupAllDayHandler(form);
}; // setupTriggers


PfyCalendar.prototype.setupAllDayHandler = function(form) {
  // All-day toggle:
  const parent = this;
  const alldayCheckbox = form.querySelector('[name=allday]');
  if (alldayCheckbox) {
    alldayCheckbox.addEventListener('change', function () {
      const allday = alldayCheckbox.checked;
      parent.setAlldayMode(form, allday);
    });
  }
}; // setupAllDayHandler


PfyCalendar.prototype.setAlldayMode = function(form, allday) {
  if (allday) {
    mylog('is allday');
    const startElem = form.querySelector('[name=start]');
    if (startElem) {
      const startVal = startElem.value;
      startElem.setAttribute('type', 'date');
      startElem.dataset.orig = startVal;
      startElem.value = startVal.substring(0,10);
    }
    const endElem = form.querySelector('[name=end]');
    if (endElem) {
      const endVal = endElem.value;
      endElem.setAttribute('type', 'date');
      endElem.dataset.orig = endVal;
      endElem.value = endVal.substring(0,10);
    }
  } else {
    mylog('not allday');
    const startElem = form.querySelector('[name=start]');
    if (startElem) {
      let startVal = startElem.dataset.orig || startElem.value;
      startElem.setAttribute('type', 'datetime-local');
      if (startVal.length < 16) {
        startVal += 'T12:00';
      }
      startElem.value = startVal;
    }
    const endElem = form.querySelector('[name=end]');
    if (endElem) {
      let endVal = endElem.dataset.orig || endElem.value;
      endElem.setAttribute('type', 'datetime-local');
      if (endVal.length < 16) {
        endVal += 'T13:00';
      }
      endElem.value = endVal;
    }
  }
}; // setAlldayMode


PfyCalendar.prototype.setupDeleteCheckboxHandler = function(form) {
  // Delete Entry checkbox:
  const deleteCheckbox = form.querySelector('input.pfy-cal-delete-entry');
  if (deleteCheckbox) {
    deleteCheckbox.addEventListener('change', function (event) {
      event.preventDefault();
      const submitBtn = form.querySelector('input.pfy-submit.button');
      if (deleteCheckbox.checked) {
        submitBtn.value = `{{ pfy-cal-delete-entry-now }}`;
      } else {
        submitBtn.value = `{{ pfy-cal-submit-entry-now }}`;
      }
    });
  }
}; // setupDeleteCheckboxHandler


PfyCalendar.prototype.setupCategoryHandler = function(form) {
  const parent = this;
  const categoryElem = form.querySelector('select[name=category]');
  if (categoryElem) {
    const wrapper = form.closest('.pfy-popup-container');
    if (wrapper) {
      wrapper.classList.remove('pfy-scroll-hints');
    }
    categoryElem.addEventListener('change', function (event) {
      parent.updateCategoryClass(event.currentTarget);
    });
  }
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
  const cancelBtn = form.querySelector('input.pfy-cancel.button');
  if (cancelBtn) {
    cancelBtn.addEventListener('click', function (event) {
      event.preventDefault();
      parent.closePopup();
    });
  }
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
        mylog(`Attempt to modify event created by other user "${creator}" -> blocked.`);
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
        mylog(`Attempt to modify event of unauthorized category ${category} -> blocked.`);
        this.fullCal.refetchEvents();
        return `{{ pfy-cal-no-permission-others-category }}`;
      }
    } catch(e) {}
  }
  return false;
}; // checkPermission


PfyCalendar.prototype.checkFreeze = function(event, checkAgainstEnd = false) {
  if (this.options.admin || !this.freezePast) {
    return true;
  }
  if (typeof event.event !== 'undefined') {
    event = event.event;
  }
  const now = new Date().toLocaleString('sv', { timeZone: timezone }).replace(' ', 'T');
  let  d = null;
  if (typeof event.dateStr !== 'undefined') {
    d = event.dateStr;

  } else if (typeof event._instance.range !== 'undefined') {
    if (checkAgainstEnd) {
      d = event._instance.range.end.toISOString();
    } else {
      d = event._instance.range.start.toISOString();
    }
  } else {
    mylog("ERROR");
  }
  mylog(`now: ${now}  then: ${d}`);
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
  const ids = document.querySelectorAll('.pfy-popup-wrapper [id]');
  if (ids) {
    ids.forEach(function (id) {
      const idAttr = 'pfy_' + id.getAttribute('id');
      id.setAttribute('id', idAttr);
    });
  }
}; // openPopup


PfyCalendar.prototype.closePopup = function() {
  pfyPopupClose(this.$formWrapper);
}; // closePopup


PfyCalendar.prototype.presetForm = function(form, recKey) {
  if (typeof recKey === 'undefined') {
    return;
  }
  const parent = this;
  let args = 'getRec='+recKey+'&datasrcinx='+this.inx;
  mylog('fetching data record '+recKey);
  execAjaxPromise(args, {})
    .then(function (data) {
      if (data.allday) {
        mylog('allday');
        parent.setAlldayMode(form, true);
      }
      pfyFormsHelper.presetForm(form, data, recKey);
      const categoryElem = form.querySelector('select[name=category]');
      parent.updateCategoryClass(categoryElem);

      const popup = document.querySelector('.pfy-popup-wrapper');
      popup.style.opacity = 1;

    })
    .then(function (msg) {
      if (msg) {
        mylog(msg);
      }
    });
}; // resetForm


PfyCalendar.prototype.modifyEvent = function(event0, start, end) {
  const fullCal = this.fullCal;
  const eventEl = event0.el;
  const span = eventEl.querySelector('.fc-event-main > span');
  const recKey = span.dataset.reckey;
  if (typeof recKey === 'undefined') {
    return;
  }

  if (event0.event._def.allDay) {
    start = start.substring(0,10);
    end = end.substring(0,10);
  }

  let args = 'modifyRec='+recKey+'&datasrcinx='+this.inx;
  args += `&start=${start}&end=${end}&calendar`;
  mylog('modifying data record '+recKey);
  execAjaxPromise(args, {})
    .then(function (data) {
      // update calendar:
      fullCal.refetchEvents();
    })
    .then(function (msg) {
      mylog(msg);
    });
}; // modifyEvent


PfyCalendar.prototype.showAlertPopup = function (str) {
  pfyAlert({content: str});
}; // showAlertPopup


PfyCalendar.prototype.setupSwipe = function() {
  const parent = this;
  swipedetect(this.$elem, function(swipedir){
    if (swipedir === 'left') {
      mylog('swiped left');
      parent.fullCal.next();
    } else if (swipedir === 'right') {
      mylog('swiped right');
      parent.fullCal.prev();
    }
  });
}; // setupSwipe
