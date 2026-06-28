const fs = require('fs');
const path = require('path');

function getMappedSize(size) {
    if (size < 13) return 12;
    if (size <= 16) return 16;
    if (size <= 21) return 20;
    if (size <= 26) return 24;
    if (size <= 35) return 32;
    return 48;
}

function walk(dir) {
    let results = [];
    const list = fs.readdirSync(dir);
    list.forEach(file => {
        const filePath = path.join(dir, file);
        const stat = fs.statSync(filePath);
        if (stat && stat.isDirectory()) {
            results = results.concat(walk(filePath));
        } else if (filePath.endsWith('.tsx') || filePath.endsWith('.ts')) {
            results.push(filePath);
        }
    });
    return results;
}

const pagesDir = path.join(__dirname, '../resources/js/Pages');
const componentsDir = path.join(__dirname, '../resources/js/Components');
let files = [];
if (fs.existsSync(pagesDir)) files = files.concat(walk(pagesDir));
if (fs.existsSync(componentsDir)) files = files.concat(walk(componentsDir));

let changedFiles = 0;

files.forEach(file => {
    let content = fs.readFileSync(file, 'utf8');
    let originalContent = content;

    const genericRegex = /<([A-Z][a-zA-Z0-9]*|[a-zA-Z0-9]+\.[a-zA-Z0-9]+)\b([^>]*?)size=\{?(\d+)\}?([^>]*?)\/?>/g;
    
    content = content.replace(genericRegex, (match, tagName, before, sizeStr, after) => {
        const size = parseInt(sizeStr, 10);
        const newSize = getMappedSize(size);
        if (size !== newSize) {
            return match.replace(new RegExp(`size=\\{?${sizeStr}\\}?`), `size={${newSize}}`);
        }
        return match;
    });

    if (content !== originalContent) {
        fs.writeFileSync(file, content, 'utf8');
        changedFiles++;
        console.log(`Updated ${file}`);
    }
});

console.log(`Standardized icon sizes in ${changedFiles} files.`);
