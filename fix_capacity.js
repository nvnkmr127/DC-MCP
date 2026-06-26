const fs = require('fs');

let content = fs.readFileSync('resources/js/Pages/Capacity/Index.tsx', 'utf8');

// Replace AppLayout import and usage with WorkloadLayout
content = content.replace(
    /import AppLayout from '@\/Layouts\/AppLayout';/,
    "import WorkloadLayout from '@/Layouts/WorkloadLayout';"
);

// We need to replace the root <AppLayout> with <WorkloadLayout currentTab="capacity">
// We also remove the explicit <div className="mb-8">...</div> header if it exists.
content = content.replace(
    /<AppLayout title="Capacity Planning">([\s\S]*?)<\/AppLayout>/,
    (match, inner) => {
        // Let's remove the header div
        const headerRegex = /<div className="flex justify-between items-center mb-8">[\s\S]*?<\/div>\s*(<div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">)/;
        inner = inner.replace(headerRegex, '$1');
        return `<WorkloadLayout title="Team Capacity" currentTab="capacity">${inner}</WorkloadLayout>`;
    }
);

fs.writeFileSync('resources/js/Pages/Capacity/Index.tsx', content);
console.log('Updated Capacity/Index.tsx');
