import Controller from "Manadev_Framework_Js/Controller";
import macaw from "Manadev_Framework_Js/vars/macaw";
import PopupMenu from "Manadev_Ui_PopupMenus/PopupMenu";

export default class SampleViewUsingPopupMenu extends Controller {
    get events() {
        return Object.assign({}, super.events, {
            'click &__button': 'onButtonClick'
        });
    }

    get menu() {
        return macaw.get('#' + this.getAliasedId('&__popup_menu'), PopupMenu);
    }

    onAttach() {
        super.onAttach();
    }

    onButtonClick(e) {
        this.menu.open(e.currentTarget);
    }
};