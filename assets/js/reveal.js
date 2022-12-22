/*
 * reveal.js
 *
 * requires jq-focusable.js
 */

var pfyRevealTransitionTime = 300;

$('.pfy-reveal-controller').each(function() {
  let $revealController = $(this);
  $revealController.attr('aria-expanded','false');
  let target = $(this).attr('data-reveal-target');
  let $revealContainer = null;
  if (typeof target !== 'undefined') {
    $revealContainer = $(target);
  } else {
    $revealContainer = $('> .pfy-reveal-container', $(this).parent());
  }
  $revealContainer.addClass('pfy-reveal-container');
  let $revealContent = $revealContainer.html();
  $revealContainer.html('<div class="pfy-reveal-container-inner" style="display: none;"></div>');
  $('.pfy-reveal-container-inner', $revealContainer).html($revealContent);
  $revealContainer.show();
  $revealContainer.find(':focusable').each(function() {
    let $this = $( this );
    let tabindex = $this.attr('tabindex');
    if ((typeof tabindex !== 'number') || (tabindex < 0)) {
      tabindex = 0;
    }
    $this.attr('data-tabindex', tabindex).addClass('pfy-focusable');
  });
  pfyDisableFocus($revealContainer);
});


// setup triggers:
$('body').on('click', '.pfy-reveal-controller', function(e) {
	e.stopImmediatePropagation();
	e.stopPropagation();
  let $controllerSelector = $(this);
  let targetSelector = $controllerSelector.data('reveal-target');
  if (typeof targetSelector !== 'undefined') {
    pfyToggleReveal(targetSelector, $controllerSelector);
  }
});


function pfyToggleReveal(targetSelector, $revealController)
{
  let state = ($revealController.attr('aria-expanded') !== 'false');
  if (state) {
    pfyUnReveal(targetSelector, $revealController);
  } else {
    pfyReveal(targetSelector, $revealController);
  }
} // pfyToggleReveal



function pfyReveal(targetSelector, controllerSelector)
{
  let $revealContainer = targetSelector;
  if (typeof targetSelector === 'string') {
    $revealContainer = $(targetSelector);
  }
  let $target = $('.pfy-reveal-container-inner', $revealContainer);
  let $revealController = controllerSelector;
  if (typeof controllerSelector === 'string') {
    $revealController = $(controllerSelector);
  }

  $target.show();

  // determine height and put target to starting position:
  const boundingBox = $target[0].getBoundingClientRect();
  const marginTop = (-0 - Math.round(boundingBox.height)) + 'px';
  $target.css({ marginTop: marginTop });

  setTimeout(function () {
    $revealContainer.addClass('pfy-elem-revealed');
    $target.animate({ marginTop: 0 }, pfyRevealTransitionTime);
    $revealController.attr('aria-expanded', 'true');
    if ($revealController) {
      $revealController.parent().addClass('pfy-target-revealed');
    }
  }, 30);

  pfyEnableFocus($revealContainer);
} // pfyReveal



function pfyUnReveal(targetSelector, controllerSelector)
{
  let $revealContainer = targetSelector;
  if (typeof targetSelector === 'string') {
    $revealContainer = $(targetSelector);
  }
  let $target = $('.pfy-reveal-container-inner', $revealContainer);
  let $revealController = controllerSelector;
  if (typeof controllerSelector === 'string') {
    $revealController = $(controllerSelector);
  }

  const boundingBox = $target[0].getBoundingClientRect();
  const marginTop = -(Math.round(boundingBox.height));

  $target.animate({ marginTop: marginTop }, pfyRevealTransitionTime);

  setTimeout(function () {
    $revealContainer.removeClass('pfy-elem-revealed');
    $revealController.attr('aria-expanded', 'false');
    $revealController.parent().removeClass('pfy-target-revealed');
    $target.hide();
  }, pfyRevealTransitionTime);

  pfyDisableFocus($revealContainer);
} // pfyUnReveal


function pfyEnableFocus($container)
{
  $('.pfy-focusable', $container).each(function () {
    let $el = $( this );
    let tabindex = $el.attr('data-tabindex');
    $el.attr('tabindex', tabindex);
  });
} // pfyEnableFocus


function pfyDisableFocus($container)
{
  $('.pfy-focusable', $container).attr('tabindex', -1);
} // pfyDisableFocus
