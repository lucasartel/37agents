/* WPAgents - Comment Suggest Reply */
(function($) {
    'use strict';

    var currentCommentId = 0;

    $(document).on('click', '.wpagent-suggest-reply', function(e) {
        e.preventDefault();
        currentCommentId = $(this).data('comment-id');

        $('#wpagent-suggest-text').val('');
        $('#wpagent-suggest-use').prop('disabled', true);
        $('#wpagent-suggest-status').html('<p>' + wpagentCommentSuggest.l10n.generating + '</p>');
        $('#wpagent-suggest-overlay, #wpagent-suggest-modal').show();

        $.ajax({
            url: wpagentCommentSuggest.restUrl,
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', wpagentCommentSuggest.nonce);
            },
            contentType: 'application/json',
            data: JSON.stringify({ comment_id: currentCommentId, tone: 'friendly' }),
            success: function(res) {
                $('#wpagent-suggest-status').html('');
                $('#wpagent-suggest-text').val(res.suggestion);
                $('#wpagent-suggest-use').prop('disabled', false);
            },
            error: function() {
                $('#wpagent-suggest-status').html('<p style="color:red;">' + wpagentCommentSuggest.l10n.error + '</p>');
            }
        });
    });

    $(document).on('click', '#wpagent-suggest-close, #wpagent-suggest-overlay', function() {
        $('#wpagent-suggest-overlay, #wpagent-suggest-modal').hide();
    });

    $(document).on('click', '#wpagent-suggest-use', function() {
        var suggestion = $('#wpagent-suggest-text').val();
        var replyBtn = $('#replyrow').find('#replycontent');
        if (replyBtn.length) {
            replyBtn.val(suggestion);
        } else {
            // Try to open the quick-reply for this comment
            $('[data-comment-id="' + currentCommentId + '"].wpagent-suggest-reply').closest('tr').find('.reply a').trigger('click');
            setTimeout(function() {
                $('#replycontent').val(suggestion);
            }, 300);
        }
        $('#wpagent-suggest-overlay, #wpagent-suggest-modal').hide();
    });

})(jQuery);
