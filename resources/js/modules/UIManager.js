export class UIManager {
    constructor(badgeDetails, switchCallback) {
        this.badgeDetails = badgeDetails;
        this.switchCallback = switchCallback;
        this.titleElement = document.getElementById('badge-title');
        this.descriptionElement = document.getElementById('badge-description');
        this.buttons = document.querySelectorAll('.badge-button');

        this.buttons.forEach(button => {
            button.addEventListener('click', (e) => this.handleSwitch(e.target.dataset.badge));
        });
    }

    handleSwitch(badgeName) {
        this.switchCallback(badgeName);
        this.updateText(badgeName);
        this.updateActiveButton(badgeName);
    }

    updateText(badgeName) {
        this.titleElement.textContent = this.badgeDetails[badgeName].title;
        this.descriptionElement.textContent = this.badgeDetails[badgeName].description;
    }

    updateActiveButton(badgeName) {
        this.buttons.forEach(btn => {
            btn.classList.toggle('active', btn.dataset.badge === badgeName);
        });
    }
}
