(($, Drupal, once) => {
  const source = '//cdnjs.cloudflare.com/ajax/libs/ace/1.11.2/ace.min.js';

  Drupal.behaviors.yamlEditor = {
    attach() {
      const initEditor = () => {
        once('yaml-editor', $('textarea[data-yaml-editor]')).forEach((item) => {
          const $textarea = $(item);
          const $editDiv = $('<div>').insertBefore($textarea);

          $textarea.addClass('visually-hidden');

          // Init ace editor.
          const editor = window.ace.edit($editDiv[0]);
          editor.getSession().setValue($textarea.val());
          editor.getSession().setTabSize(2);
          editor.setOptions({
            minLines: 3,
            maxLines: 20,
          });

          // Update Drupal textarea value.
          editor.getSession().on('change', () => {
            $textarea.val(editor.getSession().getValue());
          });
        });
      };

      // Check if Ace editor is already available and load it from source cdn otherwise.
      if (typeof window.ace !== 'undefined') {
        initEditor();
      } else {
        $.getScript(source, initEditor);
      }
    },
  };
})(jQuery, Drupal, once);
