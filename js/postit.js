// postit.js

window.onload = function() {
  document.addEventListener("click", function (ev) {
    const targetEl = ev.target.closest(".pfy-post-it");
    if (!targetEl) return;

    // Get computed ::before pseudo-element
    const beforeStyle = getComputedStyle(targetEl, "::before");
    if (!beforeStyle) return;

    // Create a temporary element to get ::before’s actual rendered box
    const pseudo = document.createElement("div");
    const cs = beforeStyle;
    Object.assign(pseudo.style, {
      position: "absolute",
      top: cs.top,
      left: cs.left,
      width: cs.width,
      height: cs.height,
      transform: cs.transform,
      transformOrigin: cs.transformOrigin,
      pointerEvents: "auto",
      background: "transparent",
    });

    // Append inside target element temporarily
    targetEl.appendChild(pseudo);

    // Get the pseudo-element’s *actual on-screen* rectangle
    const box = pseudo.getBoundingClientRect();
    targetEl.removeChild(pseudo);

    // Now compare click coordinates with that box (in viewport space)
    if (
      ev.clientX >= box.left &&
      ev.clientX <= box.right &&
      ev.clientY >= box.top &&
      ev.clientY <= box.bottom
    ) {
      ev.stopImmediatePropagation();
      ev.preventDefault();
      // Example action:
       targetEl.style.display = "none";
    }
  });
};
