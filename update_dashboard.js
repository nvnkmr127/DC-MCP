const fs = require('fs');

let content = fs.readFileSync('resources/js/Pages/Dashboard/Index.tsx', 'utf8');

// Update Props interface
const propsRegex = /interface Props {([\s\S]*?)setup_checklist: Array<\{ id: string; title: string; done: boolean; href: string \| null \}>;\n}/;
content = content.replace(propsRegex, `interface Props {
    stats: DashboardStats;
    briefing?: { id: string; date: string; digest_text: string | null; status: string } | null;
    setup_checklist: Array<{ id: string; title: string; done: boolean; href: string | null }>;
    overdue_tasks_list: Array<{ id: string; title: string; due_date: string; status: string; project?: { name: string }; assignee?: { name: string } }>;
    today_calendar: Array<{ id: string; title: string; date: string; status: string; priority: string; type: string; project?: { name: string }; url: string }>;
    pending_approvals: Array<{ id: string; title: string; status: string; project?: { name: string }; client?: { name: string }; submitter?: { name: string }; created_at: string }>;
}`);

// Update function signature
content = content.replace(
    /export default function DashboardIndex\(\{ stats, briefing, setup_checklist \}: Props\) \{/,
    'export default function DashboardIndex({ stats, briefing, setup_checklist, overdue_tasks_list = [], today_calendar = [], pending_approvals = [] }: Props) {'
);

// Add icons to imports
content = content.replace(
    /import \{\s*CheckSquare, Clock, AlertTriangle, TrendingUp, TrendingDown,\s*FolderKanban, Users, ArrowRight, Zap, Minus, LayoutGrid, Edit3, Save, Plus, X, RefreshCw, Share2, Printer\s*\} from 'lucide-react';/,
    "import { CheckSquare, Clock, AlertTriangle, TrendingUp, TrendingDown, FolderKanban, Users, ArrowRight, Zap, Minus, LayoutGrid, Edit3, Save, Plus, X, RefreshCw, Share2, Printer, Calendar, FileCheck, CheckCircle2 } from 'lucide-react';"
);

// Replace the fallback layout
const fallbackStartStr = `{/* Dashboard grid columns */}`;
const fallbackEndStr = `{/* Share Modal */}`;

const startIndex = content.indexOf(fallbackStartStr);
const endIndex = content.indexOf(fallbackEndStr);

if (startIndex !== -1 && endIndex !== -1) {
    const newFallback = `
                    {/* Command Center: Start-of-Day Flow */}
                    <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-6">
                        
                        {/* Daily Briefing & Agenda (Left Column, takes 8 cols) */}
                        <div className="lg:col-span-8 flex flex-col gap-6">
                            
                            {/* Morning Briefing */}
                            <div className="bg-gradient-to-br from-indigo-50 to-white rounded-2xl border border-indigo-100 p-6 shadow-sm relative overflow-hidden">
                                <div className="absolute top-0 right-0 p-8 opacity-5">
                                    <Zap size={100} />
                                </div>
                                <div className="flex items-center justify-between mb-4 relative z-10">
                                    <div className="flex items-center gap-3">
                                        <div className="w-10 h-10 rounded-xl bg-indigo-600 flex items-center justify-center text-white shadow-md">
                                            <Zap size={20} className="fill-indigo-400" />
                                        </div>
                                        <div>
                                            <h3 className="text-base font-bold text-gray-900 tracking-tight">Morning Briefing</h3>
                                            <p className="text-xs font-medium text-indigo-600/80">AI-powered daily digest</p>
                                        </div>
                                    </div>
                                    <Link href="/briefings" className="text-xs font-semibold text-indigo-600 hover:text-indigo-700 bg-white px-3 py-1.5 rounded-lg border border-indigo-100 shadow-sm transition-all hover:shadow">
                                        View Full Digest
                                    </Link>
                                </div>
                                
                                <div className="relative z-10">
                                    {briefing?.digest_text ? (
                                        <div className="prose prose-sm prose-indigo max-w-none text-gray-700 leading-relaxed font-medium">
                                            {briefing.digest_text}
                                        </div>
                                    ) : (
                                        <div className="py-6 text-center bg-white/60 backdrop-blur rounded-xl border border-white/40 border-dashed">
                                            <p className="text-sm text-gray-500 mb-4">You have no briefing compiled for today.</p>
                                            <Link
                                                href="/briefings/generate"
                                                method="post"
                                                as="button"
                                                className="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-colors shadow-md shadow-indigo-200 inline-flex items-center gap-2"
                                            >
                                                <Zap size={16} /> Generate Briefing
                                            </Link>
                                        </div>
                                    )}
                                </div>
                            </div>

                            {/* Today's Calendar */}
                            <div className="bg-white rounded-2xl border border-gray-100/80 shadow-[0_1px_3px_rgba(0,0,0,0.02)] flex flex-col flex-1">
                                <div className="p-5 border-b border-gray-50 flex items-center gap-2">
                                    <div className="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                                        <Calendar size={16} />
                                    </div>
                                    <h3 className="text-sm font-bold text-gray-900 tracking-wide">Today's Agenda</h3>
                                </div>
                                <div className="p-5">
                                    {today_calendar.length > 0 ? (
                                        <div className="space-y-3">
                                            {today_calendar.map(item => (
                                                <div key={item.id} className="flex items-center gap-4 p-3 rounded-xl hover:bg-gray-50 transition-colors border border-transparent hover:border-gray-100">
                                                    <div className="w-1.5 h-10 rounded-full bg-emerald-500 shrink-0"></div>
                                                    <div className="flex-1 min-w-0">
                                                        <Link href={item.url} className="text-sm font-bold text-gray-900 hover:text-emerald-600 truncate block transition-colors">
                                                            {item.title}
                                                        </Link>
                                                        <div className="flex items-center gap-3 mt-1 text-xs font-medium">
                                                            <span className="text-gray-500 uppercase tracking-wider">{item.type}</span>
                                                            {item.project && (
                                                                <>
                                                                    <span className="text-gray-300">•</span>
                                                                    <span className="text-gray-500 truncate">{item.project.name}</span>
                                                                </>
                                                            )}
                                                        </div>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    ) : (
                                        <div className="py-12 flex flex-col items-center justify-center text-center">
                                            <Calendar size={32} className="text-gray-200 mb-3" />
                                            <p className="text-sm font-medium text-gray-500">Your agenda is clear for today.</p>
                                        </div>
                                    )}
                                </div>
                            </div>

                        </div>

                        {/* Action Items (Right Column, takes 4 cols) */}
                        <div className="lg:col-span-4 flex flex-col gap-6">
                            
                            {/* Overdue Tasks */}
                            <div className="bg-white rounded-2xl border border-rose-100/60 shadow-[0_4px_20px_rgba(225,29,72,0.03)] flex flex-col h-[320px]">
                                <div className="p-5 border-b border-rose-50 flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <div className="w-8 h-8 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center">
                                            <AlertTriangle size={16} />
                                        </div>
                                        <h3 className="text-sm font-bold text-gray-900 tracking-wide">Overdue Tasks</h3>
                                    </div>
                                    {overdue_tasks_list.length > 0 && (
                                        <span className="text-[10px] font-bold text-rose-600 bg-rose-100 px-2.5 py-1 rounded-md">
                                            {overdue_tasks_list.length}
                                        </span>
                                    )}
                                </div>
                                <div className="p-4 overflow-y-auto flex-1">
                                    {overdue_tasks_list.length > 0 ? (
                                        <div className="space-y-3">
                                            {overdue_tasks_list.map(task => (
                                                <div key={task.id} className="p-3 bg-rose-50/30 rounded-xl border border-rose-100/50">
                                                    <Link href={`/tasks/${task.id}`} className="text-sm font-semibold text-gray-900 hover:text-rose-600 block mb-1">
                                                        {task.title}
                                                    </Link>
                                                    <div className="flex items-center justify-between text-[11px] font-medium text-gray-500">
                                                        <span className="text-rose-600 bg-rose-50 px-1.5 py-0.5 rounded">{task.due_date}</span>
                                                        <span className="truncate max-w-[120px]">{task.project?.name}</span>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    ) : (
                                        <div className="h-full flex flex-col items-center justify-center text-center">
                                            <CheckCircle2 size={32} className="text-emerald-300 mb-3" />
                                            <p className="text-sm font-medium text-gray-500">No overdue tasks!</p>
                                        </div>
                                    )}
                                </div>
                            </div>

                            {/* Pending Approvals */}
                            <div className="bg-white rounded-2xl border border-amber-100/60 shadow-[0_4px_20px_rgba(217,119,6,0.03)] flex flex-col flex-1 min-h-[320px]">
                                <div className="p-5 border-b border-amber-50 flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <div className="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center">
                                            <FileCheck size={16} />
                                        </div>
                                        <h3 className="text-sm font-bold text-gray-900 tracking-wide">Pending Approvals</h3>
                                    </div>
                                    {pending_approvals.length > 0 && (
                                        <span className="text-[10px] font-bold text-amber-700 bg-amber-100 px-2.5 py-1 rounded-md">
                                            {pending_approvals.length}
                                        </span>
                                    )}
                                </div>
                                <div className="p-4 overflow-y-auto flex-1">
                                    {pending_approvals.length > 0 ? (
                                        <div className="space-y-3">
                                            {pending_approvals.map(approval => (
                                                <div key={approval.id} className="p-3 bg-amber-50/30 rounded-xl border border-amber-100/50">
                                                    <div className="text-[10px] font-bold text-amber-600 uppercase tracking-wider mb-1">
                                                        {approval.project?.name || approval.client?.name || 'General Asset'}
                                                    </div>
                                                    <div className="text-sm font-semibold text-gray-900 mb-1.5">
                                                        {approval.title}
                                                    </div>
                                                    <div className="flex items-center justify-between">
                                                        <span className="text-[11px] font-medium text-gray-500">By {approval.submitter?.name}</span>
                                                        <Link href={`/projects/${approval.project?.id || ''}`} className="text-[11px] font-bold text-indigo-600 hover:text-indigo-700">
                                                            Review &rarr;
                                                        </Link>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    ) : (
                                        <div className="h-full flex flex-col items-center justify-center text-center">
                                            <FileCheck size={32} className="text-gray-200 mb-3" />
                                            <p className="text-sm font-medium text-gray-500">No pending approvals.</p>
                                        </div>
                                    )}
                                </div>
                            </div>

                        </div>
                    </div>
                </>
            )}

            `;
    
    content = content.substring(0, startIndex) + newFallback + content.substring(endIndex);
    fs.writeFileSync('resources/js/Pages/Dashboard/Index.tsx', content);
    console.log('Successfully updated Dashboard/Index.tsx');
} else {
    console.error('Could not find fallback section markers.');
}
