/*
 * login.js
 */

window.addEventListener("load", (event) => {

  // setup button 'Login with one-time-code':
  const btnOtc = document.getElementById('pfy-login-pwless');
  if (btnOtc) {
    btnOtc.addEventListener("click", (event) => {
      // mylog('pfy-login-pwless clicked');
      const wrapper = btnOtc.closest('.pfy-login-wrapper');
      wrapper.classList.remove('pfy-login-unpw');
      wrapper.classList.add('pfy-login-otc');
    });
  }

  // setup button 'Login with password':
  const btnPw = document.getElementById('pfy-login-pw');
  if (btnPw) {
    btnPw.addEventListener("click", (event) => {
      // mylog('pfy-login-pwless clicked');
      const wrapper = btnPw.closest('.pfy-login-wrapper');
      wrapper.classList.add('pfy-login-unpw');
      wrapper.classList.remove('pfy-login-otc');
    });
  }

});
