import {danger, fail, message, warn} from "danger";

const {execSync} = require("child_process");

const added = danger.github.pr.additions;
const deleted = danger.github.pr.deletions;
if (added + deleted > 500) warn("PR is very large. Consider splitting it.");

danger.git.commits.forEach(c => {
    if (!/^(\w+):/.test(c.message)) {
        warn(`Commit "${c.message}" does not follow conventional commit format.`);
    }
});

const jsFiles = danger.git.modified_files.filter(f => f.endsWith(".js") || f.endsWith(".ts"));
if (jsFiles.length) {
    try {
        execSync(`npx eslint ${jsFiles.join(" ")} --max-warnings=0`, {stdio: "inherit"});
    } catch {
        fail("ESLint errors detected!");
    }
}

const phpFiles = danger.git.modified_files.filter(f => f.endsWith(".php"));
if (phpFiles.length) {
    try {
        execSync(`vendor/bin/phpstan analyse ${phpFiles.join(" ")}`, {stdio: "inherit"});
    } catch {
        fail("PHPStan errors detected!");
    }
}

message("Danger.js checks complete ✅");
