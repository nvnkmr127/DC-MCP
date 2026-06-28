const fs = require('fs');
const path = require('path');

function processFile(filePath) {
    let content = fs.readFileSync(filePath, 'utf8');
    
    // Check if file even has <button
    if (!content.includes('<button')) return;

    let modified = false;

    // Use a regex that matches <button ... > or <button ... />
    // We will do a manual character by character search to be safer with nested tags
    
    // A simpler approach: replace <button with <Button, and </button> with </Button>
    // But we need to parse className to strip out colors and map to variants.
    
    // Let's replace tag names first
    content = content.replace(/<button(\s|>)/g, '<Button$1');
    content = content.replace(/<\/button>/g, '</Button>');
    
    // Now find all <Button ... > and modify their classNames
    // We can match className="[^\"]*"
    content = content.replace(/<Button([^>]*)className="([^"]*)"([^>]*)>/g, (match, before, classes, after) => {
        let variant = 'default';
        let size = 'md';
        
        const c = classes;
        
        // Determine variant
        if (c.includes('bg-red') || c.includes('text-red')) variant = 'destructive';
        else if (c.includes('bg-indigo') || c.includes('bg-blue')) variant = 'default';
        else if (c.includes('border') && c.includes('bg-white')) variant = 'outline';
        else if (c.includes('bg-gray-100') || c.includes('bg-gray-200')) variant = 'secondary';
        else variant = 'ghost'; // Fallback for text-only / icon buttons
        
        // Determine size
        if (c.includes('px-2') || c.includes('px-3') || c.includes('text-xs') || c.includes('py-1')) size = 'sm';
        else if (c.includes('px-4') || c.includes('py-2')) size = 'md';
        else if (c.includes('px-5') || c.includes('px-6') || c.includes('py-3')) size = 'lg';
        else size = 'icon';
        
        // Remove styling classes that the Button component handles
        // We will just keep layout/margin classes
        const keepClasses = classes.split(' ').filter(cls => {
            if (cls.startsWith('bg-')) return false;
            if (cls.startsWith('text-')) {
                 if (cls === 'text-left' || cls === 'text-right' || cls === 'text-center') return true;
                 return false;
            }
            if (cls.startsWith('hover:bg-') || cls.startsWith('hover:text-') || cls.startsWith('focus:') || cls.startsWith('active:')) return false;
            if (cls.startsWith('border-') && !cls.startsWith('border-t') && !cls.startsWith('border-b') && !cls.startsWith('border-l') && !cls.startsWith('border-r')) return false; // strip border color, keep border-t etc if any
            if (cls === 'border' || cls === 'rounded' || cls.startsWith('rounded-') || cls === 'shadow-sm') return false;
            if (cls === 'font-semibold' || cls === 'font-medium' || cls === 'transition-colors') return false;
            
            // Should we strip padding? Button component has padding.
            // If they are margin or layout (w-full, flex, items-center, mt-4), we keep them.
            if (cls.startsWith('px-') || cls.startsWith('py-') || cls.startsWith('p-')) {
                // strip standard padding since Button provides it
                if (cls === 'px-4' || cls === 'py-2' || cls === 'px-3' || cls === 'py-1.5' || cls === 'py-1' || cls === 'px-2' || cls === 'p-1' || cls === 'p-2') return false;
            }
            
            return true;
        }).join(' ');

        // Construct new tag
        let newProps = before + (keepClasses ? `className="${keepClasses}" ` : '') + after;
        
        // Add variant and size
        if (variant !== 'default') newProps += `variant="${variant}" `;
        if (size !== 'md') newProps += `size="${size}" `;
        
        return `<Button${newProps}>`;
    });
    
    // Add import if needed
    const importStr = "import { Button } from '@/Components/ui/Button';\n";
    if (!content.includes("import { Button }") && !content.includes("import {Button}")) {
        content = content.replace(/(import .*;\n)/, `$1${importStr}`);
    }

    fs.writeFileSync(filePath, content);
    console.log(`Processed ${filePath}`);
}

function walkDir(dir) {
    const files = fs.readdirSync(dir);
    for (const file of files) {
        const fullPath = path.join(dir, file);
        if (fs.statSync(fullPath).isDirectory()) {
            walkDir(fullPath);
        } else if (fullPath.endsWith('.tsx') || fullPath.endsWith('.jsx')) {
            processFile(fullPath);
        }
    }
}

walkDir('resources/js/Pages');
