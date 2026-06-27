/**
 * Importiert alle Node-Module, damit sie sich in der Registry registrieren.
 * Reihenfolge bestimmt die Palette-Reihenfolge in der UI.
 */
import "./events";
import "./flow";
import "./variables";
import "./logic";
import "./actions";

export { allNodeSpecs, getNodeSpec } from "../registry";
