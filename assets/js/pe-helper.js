
function serverLog(str, logFileName) {
  let data = {text: str};
  if (typeof logFileName !== 'undefined') {
    data['filename'] = logFileName;
  }
  let url = hostUrl + '?log';
  $.ajax({
    method: 'POST',
    url: url,
    data: data
  });
}


function camelize(str) {
  return text.replace(/^([A-Z])|[\s-_]+(\w)/g, function(match, p1, p2, offset) {
    if (p2) return p2.toUpperCase();
    return p1.toLowerCase();
  });
} // camelize


function reloadAgent( arg, url, confirmMsg ) {
    let newUrl = window.location.pathname.replace(/\?.*/, '');
    if (typeof url !== 'undefined') {
        newUrl = url.trim();
    }
    if (typeof arg !== 'undefined') {
        newUrl = appendToUrl(newUrl, arg);
    }
    if (typeof confirmMsg !== 'undefined') {
        pfyConfirm(confirmMsg).then(function() {
            console.log('initiating page reload: "' + newUrl + '"');
            window.location.replace(newUrl);
        });
    } else {
        console.log('initiating page reload: "' + newUrl + '"');
        window.location.replace(newUrl);
    }
} // pfyReload



function execAjaxPromise(cmd, options, url) {
    return new Promise(function(resolve) {

        if (typeof url === 'undefined') {
            url = appRoot;
        }
        url = appendToUrl(url, cmd);
        $.ajax({
            method: 'POST',
            url: url,
            data: options
        })
        .done(function ( json ) {
            resolve( json );
        });
    });
} // execAjax



function appendToUrl(url, arg) {
    if (!arg) {
        return url;
    }
    arg = arg.replace(/^[?&]/, '');
    if (url.match(/\?/)) {
        url = url + '&' + arg;
    } else {
        url = url + '?' + arg;
    }
    return url;
} // appendToUrl
