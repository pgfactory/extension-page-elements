// draggable.js

document.addEventListener("DOMContentLoaded", () => {
  const draggables = document.querySelectorAll(".pfy-draggable");

  draggables.forEach(el => {
    let offsetX = 0, offsetY = 0, isDragging = false;

    // Ensure the element is positioned absolutely or relatively to move freely
    const computedStyle = window.getComputedStyle(el);
    if (computedStyle.position === "static") {
      el.style.position = "absolute";
    }

    el.style.cursor = "grab";

    el.addEventListener("mousedown", (e) => {
      e.preventDefault(); // Prevent text selection during drag
      isDragging = true;
      el.style.cursor = "grabbing";
      el.style.zIndex = 1000; // bring to front while dragging

      // Calculate offset between mouse and element top-left corner
      offsetX = e.clientX - el.offsetLeft;
      offsetY = e.clientY - el.offsetTop;

      // Add listeners to document so dragging still works if cursor leaves the element
      const onMouseMove = (e) => {
        if (!isDragging) return;
        el.style.left = (e.clientX - offsetX) + "px";
        el.style.top = (e.clientY - offsetY) + "px";
      };

      const onMouseUp = () => {
        isDragging = false;
        el.style.cursor = "grab";
        el.style.zIndex = "";
        document.removeEventListener("mousemove", onMouseMove);
        document.removeEventListener("mouseup", onMouseUp);
      };

      document.addEventListener("mousemove", onMouseMove);
      document.addEventListener("mouseup", onMouseUp);
    });
  });
});

