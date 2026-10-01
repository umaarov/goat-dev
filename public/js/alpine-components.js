// Alpine's CSP build cannot evaluate expressions (that is what lets the page drop 'unsafe-eval').
// All logic lives here; templates only reference these names (getters, no-argument methods, !negation).
document.addEventListener('alpine:init', function () {
    Alpine.data('deleteModal', function () {
        return {
            isOpen: false,
            open() { this.isOpen = true; },
            close() { this.isOpen = false; },
        };
    });

    Alpine.data('aiInsight', function () {
        const seen = {
            get() { try { return localStorage.getItem('seenAiInsightHint'); } catch (e) { return null; } },
            set() { try { localStorage.setItem('seenAiInsightHint', 'true'); } catch (e) { /* storage blocked */ } },
        };

        return {
            isPanelVisible: false,
            isExpanded: false,
            showFeatureHint: false,

            init() {
                const preference = this.$el.dataset.preference;
                if (preference !== 'hidden') this.isPanelVisible = true;
                if (preference === 'expanded') this.isExpanded = true;

                if (this.$el.dataset.authed === '1' && preference !== 'hidden' && !seen.get()) {
                    setTimeout(() => { this.showFeatureHint = true; }, 1500);
                }
            },

            togglePanel() { this.isPanelVisible = !this.isPanelVisible; },
            toggleExpanded() { this.isExpanded = !this.isExpanded; },
            dismissHint() { this.showFeatureHint = false; seen.set(); },

            get panelButtonClass() {
                return this.isPanelVisible
                    ? 'text-blue-600 dark:text-blue-400'
                    : 'text-gray-400 dark:text-gray-500 hover:text-blue-600 dark:hover:text-blue-400';
            },
            get panelButtonTitle() {
                return this.isPanelVisible ? 'Hide AI context' : 'Show AI context. You can change the default in Settings.';
            },
            get bodyClass() { return this.isExpanded ? 'max-h-screen' : 'max-h-24 overflow-hidden'; },
            get expandLabel() { return this.isExpanded ? 'Show less' : 'Show more'; },
        };
    });

    Alpine.data('ratingTabs', function () {
        const active = 'bg-white dark:bg-gray-600 text-gray-800 dark:text-gray-50';
        const idle = 'text-gray-600 dark:text-gray-300 hover:bg-white/70 dark:hover:bg-gray-700';

        return {
            tab: 'post_votes',
            isLoading: true,

            init() { setTimeout(() => { this.isLoading = false; }, 500); },

            showPostVotes() { this.tab = 'post_votes'; },
            showPostCount() { this.tab = 'post_count'; },
            showCommentLikes() { this.tab = 'comment_likes'; },
            showCommentCount() { this.tab = 'comment_count'; },

            get isPostVotes() { return this.tab === 'post_votes'; },
            get isPostCount() { return this.tab === 'post_count'; },
            get isCommentLikes() { return this.tab === 'comment_likes'; },
            get isCommentCount() { return this.tab === 'comment_count'; },

            get postVotesClass() { return this.isPostVotes ? active : idle; },
            get postCountClass() { return this.isPostCount ? active : idle; },
            get commentLikesClass() { return this.isCommentLikes ? active : idle; },
            get commentCountClass() { return this.isCommentCount ? active : idle; },
        };
    });
});
