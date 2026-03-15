(function (OC, window, $, undefined) {
  'use strict';
  $(function() {
    var longRunningCommandPatterns = [
      /^files:scan(?:\s|$)/,
      /^files:transfer-ownership(?:\s|$)/,
      /^encryption:/,
      /^fulltextsearch:/,
      /^preview:generate-all(?:\s|$)/,
      /^versions:cleanup(?:\s|$)/,
      /^trashbin:cleanup(?:\s|$)/,
      /^db:add-missing-indices(?:\s|$)/,
      /^db:add-missing-columns(?:\s|$)/,
      /^db:add-missing-primary-keys(?:\s|$)/,
      /^maintenance:repair(?:\s|$)/
    ];

    function isPotentiallyLongRunning(command) {
      var normalizedCommand = (command || '').trim().toLowerCase();
      if (!normalizedCommand) {
        return false;
      }
      return longRunningCommandPatterns.some(function(pattern) {
        return pattern.test(normalizedCommand);
      });
    }

    function confirmLongRunningCommand(command) {
      if (!isPotentiallyLongRunning(command)) {
        return true;
      }

      return window.confirm(
        'Warning: "' + command + '" can take a long time on large instances and may timeout in the browser.\n\n' +
        'Recommendation: Run this command via CLI/SSH for better reliability.\n\n' +
        'Do you want to run it anyway?'
      );
    }

    function scrollToBottom(){
      var html = $('html');
      html.scrollTop(html.prop('scrollHeight'));
    }
    var baseUrl = OC.generateUrl('/apps/occweb');
    $.get(baseUrl + '/cmd', function(response){
      $('#app-content').terminal(function(command, term) {
        switch (command) {
        case "c":
          this.clear();
          break;
        case "exit":
          this.reset();
          break;
        default:
          if (!confirmLongRunningCommand(command)) {
            term.echo('\nCommand canceled.');
            break;
          }
          var occCommand = {
            command: command
          };
          term.pause();
          $.ajax({
            url: baseUrl + '/cmd',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(occCommand)
          }).done(function (response) {
            term.echo('\n' + response).resume();
          }).fail(function (response, code) {
            term.echo('\n' + response).resume();
          });
        }
      }, {
        greetings: function (callback) {
          callback('[[;green;]' + new Date().toString().slice(0, 24) + "]\n\nPress [[;#ff5e99;]Enter] for more information on [[;#009ae3;]occ] commands.\n")
        },
        name: 'occ',
        prompt: 'occ $ ',
        completion: response,
        onResize: function(){
          scrollToBottom()
        }
      });
    });
    $('html').keypress(function(){
      scrollToBottom()
    })
  });
})(OC, window, jQuery);
