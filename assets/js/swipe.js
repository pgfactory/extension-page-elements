
function swipedetect(el, callback, supportMouseSwipe = false) {
    const threshold = 150;      // min distance traveled to be considered swipe
    const restraint = 100;      // max perpendicular distance allowed
    const allowedTime = 300;    // max time allowed to travel that distance
    const wheelThreshold = 60;
    const handleswipe = callback || function() {};

    let swipedir = 'none';
    let startX, startY, startTime;

    function detectDirection(distX, distY) {
        if (Math.abs(distX) >= threshold && Math.abs(distY) <= restraint) {
            return distX < 0 ? 'left' : 'right';
        }
        if (Math.abs(distY) >= threshold && Math.abs(distX) <= restraint) {
            return distY < 0 ? 'up' : 'down';
        }
        return 'none';
    }

    // wheel action (resp. 2-finger swipe on macOS):
    el.addEventListener('wheel', function(e) {
        if (Math.abs(e.deltaX) > 10 && Math.abs(e.deltaY) < 5) {
            e.preventDefault();
        }
        if (e.deltaX > wheelThreshold && swipedir === 'none') {
            startTime = Date.now();
            swipedir = 'left';
            handleswipe(swipedir);
        } else if (e.deltaX < -wheelThreshold && swipedir === 'none') {
            startTime = Date.now();
            swipedir = 'right';
            handleswipe(swipedir);
        } else if (Math.abs(e.deltaX) < wheelThreshold && (Date.now() - startTime > allowedTime)) {
            swipedir = 'none';
        }
    }, { passive: false });

    // mouse click-move-release gesture:
    if (supportMouseSwipe) {
        el.addEventListener('mousedown', function(e) {
            swipedir = 'none';
            startX = e.pageX;
            startY = e.pageY;
            startTime = Date.now();
            e.preventDefault();
        });

        el.addEventListener('mousemove', function(e) {
            e.preventDefault();
        });

        el.addEventListener('mouseup', function(e) {
            const elapsedTime = Date.now() - startTime;
            if (elapsedTime <= allowedTime) {
                swipedir = detectDirection(e.pageX - startX, e.pageY - startY);
            }
            handleswipe(swipedir);
            e.preventDefault();
        });
    }

    // touch gestures:
    el.addEventListener('touchstart', function(e) {
        const touchobj = e.changedTouches[0];
        swipedir = 'none';
        startX = touchobj.pageX;
        startY = touchobj.pageY;
        startTime = Date.now();
    });

    el.addEventListener('touchend', function(e) {
        const touchobj = e.changedTouches[0];
        const elapsedTime = Date.now() - startTime;
        if (elapsedTime <= allowedTime) {
            swipedir = detectDirection(touchobj.pageX - startX, touchobj.pageY - startY);
        }
        handleswipe(swipedir);
    });
} // swipedetect
