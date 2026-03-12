/*
 * login.js
 */

// Focus the email input inside the given container
function focusLoginEmail(container) {
  if (!container) return;
  setTimeout(() => {
    container.querySelector('input[name=pfyLoginEmail]')?.focus();
  }, 50);
}

// Toggle between OTC and password login modes
function switchLoginMode(el, addClass, removeClass) {
  const wrapper = el.closest('.pfy-login-wrapper');
  if (!wrapper) return;
  wrapper.classList.add(addClass);
  wrapper.classList.remove(removeClass);
  focusLoginEmail(el.closest('.pfy-form'));
}

// Setup button 'Login with one-time-code':
handleEvent('#pfy-login-pwless', (ev) => {
  ev.preventDefault();
  switchLoginMode(ev.target, 'pfy-login-otc', 'pfy-login-unpw');
});

// Setup button 'Login with password':
handleEvent('#pfy-login-pw', (ev) => {
  ev.preventDefault();
  switchLoginMode(ev.target, 'pfy-login-unpw', 'pfy-login-otc');
});

// Cancel button in login/logout form:
handleEvent('.pfy-login-box input.pfy-cancel', (ev) => {
  ev.preventDefault();
  pfyFormsHelper.reloadAgent();
});

// Auto-focus on email input field:
document.addEventListener('DOMContentLoaded', () => {
  focusLoginEmail(document.querySelector('.pfy-login-wrapper'));
});
