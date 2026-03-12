// draggable.js

document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll(".pfy-draggable").forEach(el => initDraggable(el));
});


function initDraggable(el) {
  const computedStyle = window.getComputedStyle(el);
  if (computedStyle.position === "static") {
    el.style.position = "absolute";
  }
  el.style.cursor = "grab";

  let startX, startY, startLeft, startTop, isDragging = false;
  let savedZIndex = '';

  function onDragStart(clientX, clientY) {
    isDragging = true;
    startX = clientX;
    startY = clientY;
    startLeft = el.offsetLeft;
    startTop = el.offsetTop;
    savedZIndex = el.style.zIndex;
    el.style.cursor = "grabbing";
    el.style.zIndex = "1000";
    document.body.style.userSelect = "none";
  }

  function onDragMove(clientX, clientY) {
    if (!isDragging) return;
    el.style.left = (startLeft + clientX - startX) + "px";
    el.style.top = (startTop + clientY - startY) + "px";
  }

  function onDragEnd() {
    if (!isDragging) return;
    isDragging = false;
    el.style.cursor = "grab";
    el.style.zIndex = savedZIndex;
    document.body.style.userSelect = "";
  }

  // Mouse events
  el.addEventListener("mousedown", (e) => {
    e.preventDefault();
    onDragStart(e.clientX, e.clientY);

    const onMouseMove = (e) => onDragMove(e.clientX, e.clientY);
    const onMouseUp = () => {
      onDragEnd();
      document.removeEventListener("mousemove", onMouseMove);
      document.removeEventListener("mouseup", onMouseUp);
    };

    document.addEventListener("mousemove", onMouseMove);
    document.addEventListener("mouseup", onMouseUp);
  });

  // Touch events
  el.addEventListener("touchstart", (e) => {
    const touch = e.touches[0];
    onDragStart(touch.clientX, touch.clientY);
  }, { passive: true });

  el.addEventListener("touchmove", (e) => {
    if (!isDragging) return;
    e.preventDefault(); // prevent scrolling while dragging
    const touch = e.touches[0];
    onDragMove(touch.clientX, touch.clientY);
  }, { passive: false });

  el.addEventListener("touchend", () => {
    onDragEnd();
  });
} // initDraggable
