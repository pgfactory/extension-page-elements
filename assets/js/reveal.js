/*
 * reveal.js
 *
 * requires jq-focusable.js
 */

var pfyRevealTransitionTime = 300;

$('.pfy-reveal-controller').each(function() {
  let $revealController = $(this);
  let inx = 0;
  let revealControllerId = $revealController.data('reveal-target');
  if (revealControllerId) {
    inx = revealControllerId.replace(/\D*/, '');
  } else {
    inx = $revealController.parent().attr('class');
    inx = inx.replace(/pfy-reveal-controller-wrapper-(\d+).*/, '$1');
  }
  $revealController.attr('aria-expanded','false');
  let target = $(this).attr('data-reveal-target');
  let $revealContainer = null;
  if (typeof target !== 'undefined') {
    $revealContainer = $(target);
  } else {
    $revealContainer = $('> .pfy-reveal-container', $(this).parent());
  }
  $revealContainer.addClass('pfy-reveal-container pfy-reveal-container-'+inx);
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
  let $controller = $(this);
  let $target = $($controller.data('reveal-target'));
  if ($target.length) {
    pfyToggleReveal($target, $controller);
  }
});


function pfyToggleReveal($target, $revealController)
{
  let state = !$revealController.prop('checked');
  if (state) {
    pfyUnReveal($target, $revealController);
  } else {
    pfyReveal($target, $revealController);
  }
} // pfyToggleReveal



function pfyReveal($revealContainer, $revealController)
{
  let $target = $('.pfy-reveal-container-inner', $revealContainer);

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



function pfyUnReveal($revealContainer, $revealController)
{
  let $target = $('.pfy-reveal-container-inner', $revealContainer);

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
