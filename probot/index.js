module.exports = (app) => {
    app.on(["pull_request.opened", "pull_request.synchronize"], async (context) => {
        const pr = context.payload.pull_request;
        if (pr.additions + pr.deletions > 500) {
            await context.octokit.issues.create(
                context.repo({
                    title: `🚨 Large PR: #${pr.number} ${pr.title}`,
                    body: `This PR changes ${pr.changed_files} files.\nLink: ${pr.html_url}`,
                    labels: ["critical", "review-needed"],
                    assignees: [pr.user.login],
                })
            );
        }
    });

    app.on("check_run.completed", async (context) => {
        if (context.payload.check_run.conclusion === "failure") {
            await context.octokit.issues.create(
                context.repo({
                    title: `❌ CI failed for commit ${context.payload.check_run.head_sha}`,
                    body: "Check the CI logs and fix the failures immediately.",
                    labels: ["ci-failure"],
                })
            );
        }
    });
};
