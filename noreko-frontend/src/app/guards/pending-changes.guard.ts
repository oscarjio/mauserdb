import { CanDeactivateFn } from '@angular/router';

/**
 * Generisk interface för komponenter som har osparade ändringar.
 * Implementera canDeactivate() i komponenten för att styra varningen.
 */
export interface ComponentCanDeactivate {
  canDeactivate(): boolean;
}

/**
 * Guard som varnar användaren om det finns osparade ändringar.
 * Använd med canDeactivate i routing-konfigurationen.
 */
export const pendingChangesGuard: CanDeactivateFn<ComponentCanDeactivate> = (component) => {
  if (!component.canDeactivate()) {
    return confirm('Du har osparade ändringar. Är du säker på att du vill lämna sidan?');
  }
  return true;
};
