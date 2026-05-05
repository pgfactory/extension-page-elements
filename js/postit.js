// postit.js

document.addEventListener('click', function(ev) {
  const el = ev.target.closest('.pfy-post-it-removable');
  if (!el) {
    return;
  }

  // Verify the ::before close button actually renders
  const pseudo = getComputedStyle(el, '::before');
  if (!pseudo.content || pseudo.content === 'none') {
    return;
  }

  // Compute the close button's total box size (content + padding + border)
  const btnW = parseFloat(pseudo.width)
    + parseFloat(pseudo.paddingLeft) + parseFloat(pseudo.paddingRight)
    + parseFloat(pseudo.borderLeftWidth) + parseFloat(pseudo.borderRightWidth);
  const btnH = parseFloat(pseudo.height)
    + parseFloat(pseudo.paddingTop) + parseFloat(pseudo.paddingBottom)
    + parseFloat(pseudo.borderTopWidth) + parseFloat(pseudo.borderBottomWidth);

  // The ::before is positioned at top: 0; right: 0 inside the element's padding box.
  // Use local element coordinates, because clientX/clientY + getBoundingClientRect()
  // can be wrong when the element is transformed/rotated.
  const localX = ev.offsetX;
  const localY = ev.offsetY;
  const btnLeft = el.clientWidth - btnW;
  const btnTop = 0;

  if (
    localX >= btnLeft &&
    localX <= btnLeft + btnW &&
    localY >= btnTop &&
    localY <= btnTop + btnH
  ) {
    ev.stopImmediatePropagation();
    ev.preventDefault();
    el.style.display = 'none';
  }
});
