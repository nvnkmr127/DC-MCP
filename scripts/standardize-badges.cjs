const fs = require('fs');
const path = require('path');

const PAGES_DIR = path.join(__dirname, '../resources/js/Pages');

function getFiles(dir) {
    const dirents = fs.readdirSync(dir, { withFileTypes: true });
    const files = dirents.map((dirent) => {
        const res = path.resolve(dir, dirent.name);
        return dirent.isDirectory() ? getFiles(res) : res;
    });
    return Array.prototype.concat(...files).filter(f => f.endsWith('.tsx'));
}

function processFiles() {
    const files = getFiles(PAGES_DIR);
    let modifiedFiles = 0;
    
    files.forEach(file => {
        let content = fs.readFileSync(file, 'utf8');
        
        // 1. Check if the file has a STATUS_STYLES definition
        const stylesRegex = /const\s+([A-Z_]*STATUS_STYLES)\s*:\s*Record<[^>]+>\s*=\s*\{[\s\S]*?\};/g;
        if (!stylesRegex.test(content)) {
            return;
        }
        
        console.log(`Processing: ${path.relative(PAGES_DIR, file)}`);
        
        // Remove the definition
        content = content.replace(stylesRegex, '');
        
        // 2. Replace the span blocks
        // The regex looks for <span ... className={cn(... STATUS_STYLES[variable] ... )} ...> ... </span>
        const spanRegex = /<span[^>]*className=\{cn\([^}]*STATUS_STYLES\[([^\]]+)\][^}]*\)\}[^>]*>[\s\S]*?<\/span>/g;
        content = content.replace(spanRegex, (match, varName) => {
            console.log(`  Replaced span for value: ${varName}`);
            return `<StatusBadge value={${varName}} />`;
        });
        
        // Some might not use cn() but standard template literals or conditionals
        // e.g. className={STATUS_STYLES[status]}
        const simpleSpanRegex = /<span[^>]*className=\{[^}]*STATUS_STYLES\[([^\]]+)\][^}]*\}[^>]*>[\s\S]*?<\/span>/g;
        content = content.replace(simpleSpanRegex, (match, varName) => {
            console.log(`  Replaced simple span for value: ${varName}`);
            return `<StatusBadge value={${varName}} />`;
        });

        // Some might use cn() but no children
        const selfClosingSpanRegex = /<span[^>]*className=\{cn\([^}]*STATUS_STYLES\[([^\]]+)\][^}]*\)\}[^>]*\/>/g;
        content = content.replace(selfClosingSpanRegex, (match, varName) => {
            console.log(`  Replaced self-closing span for value: ${varName}`);
            return `<StatusBadge value={${varName}} />`;
        });
        
        // 3. Add import if necessary
        if (!content.includes('StatusBadge')) {
            // Find the last import statement
            const importsMatch = content.match(/import\s+.*?from\s+['"].*?['"];/g);
            if (importsMatch) {
                const lastImport = importsMatch[importsMatch.length - 1];
                content = content.replace(lastImport, `${lastImport}\nimport { StatusBadge } from '@/Components/Shared/StatusBadge';`);
            }
        }
        
        fs.writeFileSync(file, content, 'utf8');
        modifiedFiles++;
    });
    
    console.log(`Modified ${modifiedFiles} files.`);
}

processFiles();
