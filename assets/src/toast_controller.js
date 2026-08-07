import { Controller } from "@hotwired/stimulus";

/**
 * A message shown over the editor rather than pushed into it.
 *
 * The builder takes the whole window, so a banner at the top of the page moves
 * the canvas down every time something is saved. These sit in a corner and get
 * out of the way on their own.
 *
 * Two rules the automatic dismissal follows, both from WCAG 2.2: a message can
 * always be closed by hand, and the countdown stops while the pointer is over
 * it or the keyboard focus is inside it — otherwise a message can vanish while
 * it is being read. An error never dismisses itself at all: it is the one the
 * reader has to act on.
 */
export default class extends Controller {
    static values = {
        delay: { type: Number, default: 6000 },
        // Errors stay until they are dismissed.
        permanent: { type: Boolean, default: false },
    };

    connect() {
        this.element.classList.add("cms-toast--visible");

        if (this.permanentValue || this.delayValue <= 0) {
            return;
        }

        this.pause = this.pause.bind(this);
        this.resume = this.resume.bind(this);

        this.element.addEventListener("mouseenter", this.pause);
        this.element.addEventListener("mouseleave", this.resume);
        this.element.addEventListener("focusin", this.pause);
        this.element.addEventListener("focusout", this.resume);

        this.resume();
    }

    disconnect() {
        this.clear();
        this.element.removeEventListener("mouseenter", this.pause);
        this.element.removeEventListener("mouseleave", this.resume);
        this.element.removeEventListener("focusin", this.pause);
        this.element.removeEventListener("focusout", this.resume);
    }

    close() {
        this.clear();
        this.element.classList.remove("cms-toast--visible");

        // Removed after the transition so the space it took goes with it; the
        // listener fires immediately when animations are off.
        this.element.addEventListener("transitionend", () => this.element.remove(), { once: true });
        setTimeout(() => this.element.remove(), 400);
    }

    pause() {
        this.clear();
    }

    resume() {
        this.clear();
        this.timer = setTimeout(() => this.close(), this.delayValue);
    }

    clear() {
        clearTimeout(this.timer);
    }
}
