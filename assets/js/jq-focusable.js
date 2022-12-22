// :focusable

jQuery.extend(jQuery.expr[':'], {
    focusable: function(el, index, selector){
      return $(el).is('a, button, :input, [tabindex]');
    }
  });
  