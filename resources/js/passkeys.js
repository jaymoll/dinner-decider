import { Passkeys } from '@laravel/passkeys';

window.Passkeys = Passkeys;

// Settings components can render before this deferred Vite entry finishes loading.
window.dispatchEvent(new CustomEvent('passkeys:ready'));
