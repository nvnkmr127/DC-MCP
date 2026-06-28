const fs = require('fs');
const path = require('path');

const addBreadcrumbs = (filePath, sectionLabel, sectionUrl, pageLabel) => {
    let content = fs.readFileSync(filePath, 'utf8');
    
    if (content.includes('Breadcrumbs items=')) {
        console.log(`Skipping ${filePath}, already has breadcrumbs`);
        return;
    }

    // Add import
    const importStr = "import { Breadcrumbs } from '@/Components/Shared/Breadcrumbs';\n";
    if (!content.includes(importStr)) {
        content = content.replace(/(import .*;\n)/, `$1${importStr}`);
    }

    // Replace Head tag
    const headRegex = /<Head title=({.*}|".*") \/>/g;
    const match = headRegex.exec(content);
    if (match) {
        let label = pageLabel;
        if (!label) {
             const titleMatch = match[1];
             if (titleMatch.startsWith('"')) {
                 label = titleMatch.slice(1, -1);
             } else {
                 label = titleMatch;
             }
        }
        
        const replaceStr = `${match[0]}
            <div className="mb-6">
                <Breadcrumbs items={[
                    { label: '${sectionLabel}', href: '${sectionUrl}' },
                    { label: ${label.startsWith('{') ? label.slice(1, -1) : `'${label}'`} }
                ]} />
            </div>`;
        content = content.replace(match[0], replaceStr);
        fs.writeFileSync(filePath, content);
        console.log(`Updated ${filePath}`);
    } else {
        console.log(`No Head tag found in ${filePath}`);
    }
};

const settingsFiles = fs.readdirSync('resources/js/Pages/Settings').filter(f => f.endsWith('.tsx') && f !== 'Team.tsx');
for (const file of settingsFiles) {
    addBreadcrumbs(path.join('resources/js/Pages/Settings', file), 'Settings', '/settings', null);
}

const adminFiles = fs.readdirSync('resources/js/Pages/Admin').filter(f => f.endsWith('.tsx'));
for (const file of adminFiles) {
    addBreadcrumbs(path.join('resources/js/Pages/Admin', file), 'Admin', '/admin', null);
}

