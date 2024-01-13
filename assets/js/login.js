/*
 * login.js
 */

window.addEventListener("load", (event) => {

  // setup button 'Login with one-time-code':
  const btnOtc = document.getElementById('pfy-login-pwless');
  if (btnOtc) {
    btnOtc.addEventListener("click", (event) => {
      event.preventDefault();
      const wrapper = btnOtc.closest('.pfy-login-wrapper');
      wrapper.classList.remove('pfy-login-unpw');
      wrapper.classList.add('pfy-login-otc');
      const form = event.target.closest('.pfy-form');
      setTimeout(function () {
        const input = form.querySelector('input[name=email]');
        input.focus();
      }, 50);
    });
  }

  // setup button 'Login with password':
  const btnPw = document.getElementById('pfy-login-pw');
  if (btnPw) {
    btnPw.addEventListener("click", (event) => {
      event.preventDefault();
      const wrapper = btnPw.closest('.pfy-login-wrapper');
      wrapper.classList.add('pfy-login-unpw');
      wrapper.classList.remove('pfy-login-otc');
      const form = event.target.closest('.pfy-form');
      setTimeout(function () {
        const input = form.querySelector('input[name=email]');
        input.focus();
      }, 50);
    });
  }

});
