import { copyFile, mkdir, readFile, readdir, rm, writeFile } from 'node:fs/promises';

const manifestUrl = new URL('../public/build/manifest.json', import.meta.url);
const manifest = JSON.parse(await readFile(manifestUrl, 'utf8'));
const cssFile = manifest['resources/css/app.css']?.file;
const jsFile = manifest['resources/js/app.js']?.file;

if (!cssFile || !jsFile) {
    throw new Error('Vite did not generate the expected CSS and JavaScript entries.');
}

const publicCssDirectory = new URL('../public/css/', import.meta.url);
const publicJsDirectory = new URL('../public/js/', import.meta.url);

// `public/build` esta en .gitignore y nunca se despliega: todo lo que la
// aplicacion necesita en produccion tiene que salir de ahi y quedar bajo
// `public/css` y `public/js`, que si se versionan.
const publicFontsDirectory = new URL('assets/', publicCssDirectory);

await mkdir(publicCssDirectory, { recursive: true });
await mkdir(publicJsDirectory, { recursive: true });
await mkdir(publicFontsDirectory, { recursive: true });

await copyFile(
    new URL(`../public/build/${jsFile}`, import.meta.url),
    new URL('app.js', publicJsDirectory),
);

const assetsDirectory = new URL('../public/build/assets/', import.meta.url);
const assetFiles = await readdir(assetsDirectory);

for (const file of assetFiles) {
    // Los chunks se importan con una ruta relativa (`./apexcharts-*.js`), asi
    // que basta con dejarlos junto a `app.js`.
    if (file.endsWith('.js')) {
        await copyFile(new URL(file, assetsDirectory), new URL(file, publicJsDirectory));
        continue;
    }

    // Las fuentes se publican junto al CSS. La referencia relativa permite
    // servirlas tanto desde la raiz del dominio como desde una subruta local.
    if (!file.endsWith('.css')) {
        await copyFile(new URL(file, assetsDirectory), new URL(file, publicFontsDirectory));
    }
}

// Cada build dejaba su copia con hash en `public/js`, y como ese directorio si
// se versiona, los bundles de despliegues anteriores se acumulaban en el
// repositorio. Se borra lo que ya no produce este build.
const publishedNow = new Set(['app.js', ...assetFiles.filter((file) => file.endsWith('.js'))]);

for (const file of await readdir(publicJsDirectory)) {
    if (file.endsWith('.js') && !publishedNow.has(file)) {
        await rm(new URL(file, publicJsDirectory));
    }
}

const css = await readFile(new URL(`../public/build/${cssFile}`, import.meta.url), 'utf8');
const publishedCss = css.replaceAll('/build/assets/', 'assets/');

if (publishedCss.includes('/build/')) {
    throw new Error('El CSS publicado sigue apuntando a /build/, que no se despliega.');
}

await writeFile(new URL('app.css', publicCssDirectory), publishedCss);

console.log('Published public/css/app.css, public/js/app.js and '
    + `${assetFiles.filter((file) => !file.endsWith('.js') && !file.endsWith('.css')).length} asset(s).`);
