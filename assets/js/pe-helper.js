/*
 * Helper functions for PageElements
 */


function serverLog(text, logFileName) {
  let url = window.location.href + '?ajax&log=' +  encodeURI(text);
  if (typeof logFileName !== 'undefined') {
    url += '&filename=' + encodeURI(logFileName);
  }
  mylog('url: ' + url);
  fetch(url, { headers: {'Content-Type': 'application/json'} });
} // serverLog


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
    let url = window.location.href;
    if (typeof url === 'undefined') {
      url = pageUrl;
    }
    url = appendToUrl(url, cmd, 'ajax');
    if (typeof options === 'undefined') {
      options = {};
    }
    fetch(url, {
      method: 'POST',
      body: JSON.stringify(options),
      headers: {
        'Content-Type': 'application/json'
      }
    })
      .then(function(response) {
        return response.json();
      })
      .then(function(json) {
        resolve(json);
      });
  });
}



function appendToUrl(url, arg, arg2) {
    if (!arg) {
        return url;
    }
    arg = arg.replace(/^[?&]/, '');

    if (typeof arg2 !== 'undefined') {
      arg = arg + '&' + arg2;
    }

    if (url.match(/\?/)) {
        url = url + '&' + arg;
    } else {
        url = url + '?' + arg;
    }
    return url;
} // appendToUrl
