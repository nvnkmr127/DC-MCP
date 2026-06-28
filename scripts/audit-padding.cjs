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

// Matches classes like p-4, px-6, py-8, sm:px-6, lg:px-8, pt-4, pb-12
const PADDING_REGEX = /\b(sm:|md:|lg:|xl:|2xl:)?(p|px|py|pt|pb|pl|pr)-\d+\b/g;

function analyzeFiles() {
    const files = getFiles(PAGES_DIR);
    let totalFiles = 0;
    let modifiedFiles = 0;
    
    files.forEach(file => {
        let content = fs.readFileSync(file, 'utf8');
        
        // Only process files that use AppLayout
        if (!content.includes('<AppLayout')) {
            return;
        }
        
        totalFiles++;
        
        // Find the first <div className="..."> or <main className="..."> after <AppLayout
        const appLayoutMatch = content.match(/<AppLayout[^>]*>([\s\S]*?)<\/AppLayout>/);
        if (!appLayoutMatch) return;
        
        const innerContent = appLayoutMatch[1];
        
        // Look for the very first tag with a className
        const firstTagMatch = innerContent.match(/<(div|main)\s+[^>]*?className=(["'])(.*?)\2[^>]*>/);
        
        if (firstTagMatch) {
            const fullMatch = firstTagMatch[0];
            const className = firstTagMatch[3];
            
            // Only process if it's a true page wrapper (contains max-w- or mx-auto)
            if ((className.includes('max-w-') || className.includes('mx-auto')) && PADDING_REGEX.test(className)) {
                
                console.log(`[${path.relative(PAGES_DIR, file)}]`);
                console.log(`  Found: ${className}`);
                
                const newClassName = className.replace(PADDING_REGEX, '').replace(/\s+/g, ' ').trim();
                console.log(`  New:   ${newClassName}`);
                
                const newContent = content.replace(fullMatch, fullMatch.replace(className, newClassName));
                fs.writeFileSync(file, newContent, 'utf8');
                
                console.log('---');
                modifiedFiles++;
            }
        }
    });
    
    console.log(`Total files with AppLayout: ${totalFiles}`);
    console.log(`Files with padding on first inner element: ${modifiedFiles}`);
}

analyzeFiles();
