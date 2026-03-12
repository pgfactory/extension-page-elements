// postit.js

document.addEventListener('click', function(ev) {
  const el = ev.target.closest('.pfy-post-it-removable');
  if (!el) return;

  // Verify the ::before close button actually renders
  const pseudo = getComputedStyle(el, '::before');
  if (!pseudo.content || pseudo.content === 'none') return;

  // Compute the close button's total box size (content + padding + border)
  const btnW = parseFloat(pseudo.width)
    + parseFloat(pseudo.paddingLeft) + parseFloat(pseudo.paddingRight)
    + parseFloat(pseudo.borderLeftWidth) + parseFloat(pseudo.borderRightWidth);
  const btnH = parseFloat(pseudo.height)
    + parseFloat(pseudo.paddingTop) + parseFloat(pseudo.paddingBottom)
    + parseFloat(pseudo.borderTopWidth) + parseFloat(pseudo.borderBottomWidth);

  // The ::before is at top:0, right:0 relative to the element's padding box
  const rect = el.getBoundingClientRect();
  const elStyle = getComputedStyle(el);
  const btnLeft = rect.right - (parseFloat(elStyle.borderRightWidth) || 0) - btnW;
  const btnTop = rect.top + (parseFloat(elStyle.borderTopWidth) || 0);

  if (
    ev.clientX >= btnLeft &&
    ev.clientX <= btnLeft + btnW &&
    ev.clientY >= btnTop &&
    ev.clientY <= btnTop + btnH
  ) {
    ev.stopImmediatePropagation();
    ev.preventDefault();
    el.style.display = 'none';
  }
});
