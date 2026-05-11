    <script>
(function() {
    function toggleIdentified(id, show) {
        var el = document.getElementById(id);
        if (el) el.style.display = show ? '' : 'none';
    }
    var sel = document.getElementById('identityModeSelect');
    if (sel) {
        sel.addEventListener('change', function() {
            var show = this.value === 'identified';
            toggleIdentified('identifiedOptions', show);
            toggleIdentified('identifiedOptionsEdit', show);
        });
        // Set initial state
        toggleIdentified('identifiedOptions', sel.value === 'identified');
        toggleIdentified('identifiedOptionsEdit', sel.value === 'identified');
    }
})();
</script>
    </main>

    <footer class="text-center py-4 text-muted text-sm">
        <div class="container">
            <?php echo APP_NAME; ?> v<?php echo APP_VERSION; ?> - A Web-Based Questionnaire System
        </div>
    </footer>
</body>
</html>