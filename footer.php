</main>

    <div class="notification" id="notification"></div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Common scripts for notifications
        let lastTotalUnread = <?php echo $total_unread; ?>;

        function checkNewMessages() {
            $.get('get_total_unread_messages.php', function(data) {
                if (!data || !data.total_unread) {
                    console.log("No unread messages data");
                    return;
                }

                const totalUnread = parseInt(data.total_unread);
                if (totalUnread > lastTotalUnread) {
                    const notification = $('#notification');
                    notification.text(`<?php echo $lang['new_message'] ?? 'New Message'; ?>: ${totalUnread}`);
                    notification.fadeIn(500).delay(3000).fadeOut(500);

                    const audio = new Audio('sounds/notification.mp3');
                    audio.play().catch(error => console.log("Error playing sound:", error));

                    lastTotalUnread = totalUnread;
                }

                const sidebarUnread = $('.sidebar-unread-count');
                if (totalUnread > 0) {
                    if (sidebarUnread.length) {
                        sidebarUnread.text(totalUnread);
                    } else {
                        $('a[href="admin_dashboard.php?page=messages"]').append(`<span class="sidebar-unread-count">${totalUnread}</span>`);
                    }
                } else {
                    sidebarUnread.remove();
                }
            }).fail(function() {
                console.log("Error fetching unread messages");
            });
        }

        $(document).ready(function() {
            setInterval(checkNewMessages, 5000);
        });
    </script>
</body>
</html>