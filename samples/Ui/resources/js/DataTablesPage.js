import Controller from "Manadev_Framework_Js/Controller";

export default class DataTablesPage extends Controller {
    get events() {
        return Object.assign({}, super.events, {
            // handle JS events here
        });
    }
};