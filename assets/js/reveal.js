/*
 * reveal.js
 *
 */

var pfyReveal = {
  transitionTime: 300,

  initialize: function() {
    const revealControllers = document.querySelectorAll('input.pfy-reveal-controller');
    if (revealControllers) {
      revealControllers.forEach(function (revealController) {
        pfyReveal.init(revealController);
        pfyReveal.setupEventHandler(revealController);
      });
    }
  }, // initialize

  init: function(revealController) {
        var inx = 0;
        const revealContainerId = revealController.dataset.revealTarget;
        if (revealContainerId) {
          const m = revealContainerId.match(/[\d_]*$/);
          inx = m[0];
        } else {
          const par = revealController.parentNode;
          if (par) {
            const className = par.className;
            const m = className.match(/[\d_]*$/);
            inx = m[0];
          }
          if (!inx) {
            return;
          }
        }

        revealController.setAttribute('aria-expanded', 'false');
        var revealContainer = null;
        if (typeof revealContainerId !== 'undefined') {
          revealContainer = document.querySelector(revealContainerId);
        } else {
          revealContainer = revealController.parentNode.querySelector('> .pfy-reveal-container');
        }
        revealContainer.classList.add('pfy-reveal-container', 'pfy-reveal-container-' + inx);
        var revealContent = revealContainer.innerHTML;

        if (!revealContainer.querySelector('.pfy-reveal-container-inner')) {
          revealContainer.innerHTML = '<div class="pfy-reveal-container-inner" style="display: none;"></div>';
          revealContainer.querySelector('.pfy-reveal-container-inner').innerHTML = revealContent;
        }
        revealContainer.style.display = 'block';
        pfyReveal.disableFocus(revealContainer);
  }, // initialize


  setupEventHandler: function (revealController) {
    revealController.addEventListener('click', function (event) {
      var revealController = event.target.closest('.pfy-reveal-controller');
      if (revealController) {
        var target = document.querySelector(revealController.getAttribute('data-reveal-target'));
        if (target) {
          pfyReveal.toggle(target, revealController);
        }
      }
    });
  }, // setupEventHandler


  toggle: function(revealContainer, revealController) {
    var state = !revealController.checked;
    if (state) {
      pfyReveal.unreveal(revealContainer, revealController);
    } else {
      pfyReveal.reveal(revealContainer, revealController);
    }
  }, // toggle


  reveal: function(revealContainer, revealController) {
    var target = revealContainer.querySelector('.pfy-reveal-container-inner');

    target.style.display = 'block';

    const boundingBox = target.getBoundingClientRect();
    target.style.marginTop = (0 - Math.round(boundingBox.height)) + 'px';

    setTimeout(function () {
      revealContainer.classList.add('pfy-elem-revealed');
      pfyReveal.animate(target, 'marginTop', 0, pfyReveal.transitionTime);
      revealController.setAttribute('aria-expanded', 'true');
      if (revealController) {
        revealController.parentNode.classList.add('pfy-target-revealed');
      }
    }, 30);

    pfyReveal.enableFocus(revealContainer);
  }, // reveal


  unreveal: function(revealContainer, revealController) {
    var target = revealContainer.querySelector('.pfy-reveal-container-inner');

    var boundingBox = target.getBoundingClientRect();
    var marginTop = -(Math.round(boundingBox.height));

    pfyReveal.animate(target, 'marginTop', marginTop, pfyReveal.transitionTime);

    setTimeout(function () {
      revealContainer.classList.remove('pfy-elem-revealed');
      revealController.setAttribute('aria-expanded', 'false');
      revealController.parentNode.classList.remove('pfy-target-revealed');
      target.style.display = 'none';
    }, pfyReveal.transitionTime);

    pfyReveal.disableFocus(revealContainer);
  }, // unreveal


  enableFocus: function(container) {
    Array.from(container.querySelectorAll('.pfy-focusable')).forEach(function (element) {
      var tabindex = element.getAttribute('data-tabindex');
      element.setAttribute('tabindex', tabindex);
    });
  }, // enableFocus


  disableFocus: function(container) {
    Array.from(container.querySelectorAll('.pfy-focusable')).forEach(function (element) {
      element.setAttribute('tabindex', -1);
    });
  }, // disableFocus


  animate: function(element, property, value, duration) {
    var start = null;
    var initialValue = parseFloat(getComputedStyle(element)[property]);
    function step(timestamp) {
      if (!start) start = timestamp;
      var progress = timestamp - start;
      if (progress >= duration) progress = duration;
      var current = initialValue + ((value - initialValue) * (progress / duration));
      element.style[property] = current + 'px';
      if (progress < duration) {
        window.requestAnimationFrame(step);
      }
    }
    window.requestAnimationFrame(step);
  }, // animate
}; // pfyReveal

pfyReveal.initialize();


function pfyRevealPanel(arg)
{
  let controller;
  if (typeof arg === 'string') {
    controller = document.querySelector(arg);
  } else {
    controller = arg;
  }
  if (!controller) {
    return;
  }
  let wrapper = controller.parentNode.closest('.pfy-reveal-controller');
  if (!wrapper) {
    return;
  }
  const revealTargetStr = controller.getAttribute('data-reveal-target');
  const revealTarget = document.querySelector(revealTargetStr);
  if (!revealTarget) {
    return;
  }
  pfyReveal.reveal(revealTarget, controller);
} // pfyRevealPanel


function pfyUnrevealPanel(arg)
{
  let controller;
  if (typeof arg === 'string') {
    controller = document.querySelector(arg);
  } else {
    controller = arg;
  }
  if (!controller) {
    return;
  }
  let wrapper = controller.parentNode.closest('.pfy-reveal-controller');
  if (!wrapper) {
    return;
  }
  const revealTargetStr = controller.getAttribute('data-reveal-target');
  const revealTarget = document.querySelector(revealTargetStr);
  if (!revealTarget) {
    return;
  }
  pfyReveal.unreveal(revealTarget, controller);
} // pfyUnrevealPanel
