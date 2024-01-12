
function swipedetect(el, callback, supportMouseSwipe = false){
    var touchsurface = el,
    swipedir = 'none',
    startX,
    startY,
    distX,
    distY,
    threshold = 150, //required min distance traveled to be considered swipe
    restraint = 100, // maximum distance allowed at the same time in perpendicular direction
    allowedTime = 300, // maximum time allowed to travel that distance
    wheelThreshold = 60,
    elapsedTime,
    startTime,
    handleswipe = callback || function(swipedir){};

    // wheel action (resp. 2-finger swipe on MacOS):
    touchsurface.addEventListener('wheel', function(e){
      if (Math.abs(e.deltaY) < 5 || Math.abs(e.deltaX) > 10) {
        e.preventDefault();
      }
      if ((e.deltaX > wheelThreshold) && swipedir === 'none') {
          startTime = new Date().getTime(); // record time when finger first makes contact with surface
          swipedir = 'left';
          handleswipe(swipedir);

        } else if ((e.deltaX < -wheelThreshold) && swipedir === 'none') {
          startTime = new Date().getTime(); // record time when finger first makes contact with surface
          swipedir = 'right';
          handleswipe(swipedir);

        } else if ((Math.abs(e.deltaX) < wheelThreshold) && (new Date().getTime() - startTime > allowedTime)) {
          swipedir = 'none';
        }
    }, false);

    // mouse click-move-release gesture:
    if (supportMouseSwipe) {
      touchsurface.addEventListener('mousedown', function (e) {
        swipedir = 'none';
        dist = 0;
        startX = e.x;
        startY = e.y;
        startTime = new Date().getTime();
        e.preventDefault();
      }, false);

      touchsurface.addEventListener('mousemove', function (e) {
        e.preventDefault();
      }, false);

      touchsurface.addEventListener('mouseup', function (e) {
        distX = e.x - startX;
        distY = e.y - startY;
        elapsedTime = new Date().getTime() - startTime;
        if (elapsedTime <= allowedTime) {
          if (Math.abs(distX) >= threshold && Math.abs(distY) <= restraint) {
            swipedir = (distX < 0) ? 'left' : 'right';
          } else if (Math.abs(distY) >= threshold && Math.abs(distX) <= restraint) {
            swipedir = (distY < 0) ? 'up' : 'down';
          }
        }
        handleswipe(swipedir);
        e.preventDefault();
      }, false);
    } // supportMouseSwipe


    // touch gestures:
    touchsurface.addEventListener('touchstart', function(e) {
      var touchobj = e.changedTouches[0];
      swipedir = 'none';
      dist = 0;
      startX = touchobj.pageX;
      startY = touchobj.pageY;
      startTime = new Date().getTime();
//      e.preventDefault();
    }, false);

    touchsurface.addEventListener('touchmove', function(e) {
//      e.preventDefault();
    }, false);

    touchsurface.addEventListener('touchend', function(e) {
      var touchobj = e.changedTouches[0];
      distX = touchobj.pageX - startX;
      distY = touchobj.pageY - startY;
      elapsedTime = new Date().getTime() - startTime;
      if (elapsedTime <= allowedTime) {
        if (Math.abs(distX) >= threshold && Math.abs(distY) <= restraint) {
          swipedir = (distX < 0)? 'left' : 'right';
        }
        else if (Math.abs(distY) >= threshold && Math.abs(distX) <= restraint) {
          swipedir = (distY < 0)? 'up' : 'down';
        }
      }
      handleswipe(swipedir);
//      e.preventDefault();
    }, false);
} // swipedetect

