import React, { useState } from 'react';
import { Button } from '@/Components/ui/Button';
import { Head, router, useForm } from '@inertiajs/react';
import SyncsLayout from '@/Layouts/SyncsLayout';
import { cn } from '@/lib/utils';
import { Plus, X, CheckCircle2, Circle, Users2, Search } from 'lucide-react';

const MOOD_CONFIG: Record<string, { label: string; color: string; dot: string }> = {
    great:     { label: 'Great',     color: 'text-emerald-700 bg-emerald-100', dot: 'bg-emerald-500' },
    good:      { label: 'Good',      color: 'text-blue-700 bg-blue-100',       dot: 'bg-blue-500' },
    neutral:   { label: 'Neutral',   color: 'text-gray-600 bg-gray-100',       dot: 'bg-gray-400' },
    concerned: { label: 'Concerned', color: 'text-amber-700 bg-amber-100',     dot: 'bg-amber-500' },
    struggling:{ label: 'Struggling',color: 'text-rose-700 bg-rose-100',       dot: 'bg-rose-500' },
};

interface ActionItem { id: string; text: string; done: boolean; due_date: string | null; }
interface Note {
    id: string; meeting_date: string; wins: string | null; challenges: string | null;
    action_items: ActionItem[]; mood: string | null; next_meeting_date: string | null;
    template_name?: string | null; performance_review_id?: string | null;
    manager: { id: string; name: string }; member: { id: string; name: string };
}
interface Props {
    teamMembers: { id: string; name: string }[];
    notes: Note[];
    latestByMember: Note[];
    performanceReviews: { id: string; title: string; employee_id: string; employee_name: string }[];
    templates: { id: string; name: string; questions: string }[];
}

function NoteCard({ note, defaultExpanded }: { note: Note; defaultExpanded?: boolean }) {
    const [expanded, setExpanded] = useState(defaultExpanded ?? false);
    const mood = note.mood ? MOOD_CONFIG[note.mood] : null;

    return (
        <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <Button onClick={() => setExpanded(!expanded)}
                className="w-full px-4 py-3 flex items-center justify-between hover:bg-gray-50 transition-colors text-left">
                <div className="flex items-center gap-3">
                    {mood && <span className={cn('w-2.5 h-2.5 rounded-full shrink-0', mood.dot)} />}
                    <div>
                        <p className="text-sm font-semibold text-gray-900">{note.meeting_date}</p>
                        <p className="text-xs text-gray-400">by {note.manager.name}</p>
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    {mood && (
                        <span className={cn('text-xs px-2 py-0.5 rounded-full font-medium', mood.color)}>
                            {mood.label}
                        </span>
                    )}
                    {note.template_name && (
                        <span className="text-[10px] px-2 py-0.5 bg-indigo-50 text-indigo-700 rounded-full font-medium capitalize">
                            {note.template_name.replace('_', ' ')}
                        </span>
                    )}
                    {note.performance_review_id && (
                        <span className="text-[10px] px-2 py-0.5 bg-purple-50 text-purple-700 rounded-full font-medium">
                            Review Linked
                        </span>
                    )}
                    {note.next_meeting_date && (
                        <span className="text-xs text-gray-400">Next: {note.next_meeting_date}</span>
                    )}
                </div>
            </Button>

            {expanded && (
                <div className="px-4 pb-4 space-y-3 border-t border-gray-100">
                    {note.wins && (
                        <div>
                            <p className="text-xs font-semibold text-emerald-700 mb-1">Wins</p>
                            <p className="text-sm text-gray-700 whitespace-pre-wrap">{note.wins}</p>
                        </div>
                    )}
                    {note.challenges && (
                        <div>
                            <p className="text-xs font-semibold text-rose-600 mb-1">Challenges</p>
                            <p className="text-sm text-gray-700 whitespace-pre-wrap">{note.challenges}</p>
                        </div>
                    )}
                    {note.action_items.length > 0 && (
                        <div>
                            <p className="text-xs font-semibold text-gray-500 mb-1.5">Action Items</p>
                            <div className="space-y-1">
                                {note.action_items.map(item => (
                                    <Button key={item.id} onClick={() => router.post(`/one-on-one/${note.id}/action-item`, { id: item.id })}
                                        className="flex items-center gap-2 w-full text-left group hover:bg-gray-50 rounded px-1 py-0.5">
                                        {item.done
                                            ? <CheckCircle2 className="w-4 h-4 text-emerald-500 shrink-0" />
                                            : <Circle className="w-4 h-4 text-gray-300 group-hover:text-gray-400 shrink-0" />
                                        }
                                        <span className={cn('text-sm flex-1', item.done ? 'line-through text-gray-400' : 'text-gray-700')}>
                                            {item.text}
                                        </span>
                                        {item.due_date && <span className="text-xs text-gray-400">{item.due_date}</span>}
                                    </Button>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

function AddNoteModal({ teamMembers, performanceReviews, templates, onClose }: { teamMembers: Props['teamMembers']; performanceReviews: Props['performanceReviews']; templates: Props['templates']; onClose: () => void }) {
    const form = useForm({
        member_id: '', meeting_date: new Date().toISOString().slice(0, 10),
        wins: '', challenges: '', mood: '', next_meeting_date: '',
        template_name: '', performance_review_id: '',
    });
    const [actionItems, setActionItems] = useState<{ text: string; due_date: string }[]>([]);

    function addItem() { setActionItems(prev => [...prev, { text: '', due_date: '' }]); }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        const data = {
            ...form.data,
            action_items: actionItems.filter(a => a.text).map(a => ({
                text: a.text, due_date: a.due_date || null,
            })),
        };
        router.post('/one-on-one', data, { onSuccess: onClose });
    }

    const handleTemplateChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
        const val = e.target.value;
        form.setData('template_name', val);
        const template = templates.find(t => t.id === val);
        if (template) {
            form.setData('challenges', template.questions);
        }
    };

    const filteredReviews = form.data.member_id ? performanceReviews.filter(r => r.employee_id === form.data.member_id) : [];

    return (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
            <div className="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 space-y-4 max-h-[90vh] overflow-y-auto">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-bold text-gray-900">Add 1:1 Note</h2>
                    <Button onClick={onClose}><X className="w-5 h-5 text-gray-400" /></Button>
                </div>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Team Member *</label>
                            <select value={form.data.member_id} onChange={e => form.setData('member_id', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                                <option value="">Select…</option>
                                {teamMembers.map(u => <option key={u.id} value={u.id}>{u.name}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Meeting Date *</label>
                            <input type="date" value={form.data.meeting_date} onChange={e => form.setData('meeting_date', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Use Template</label>
                            <select value={form.data.template_name} onChange={handleTemplateChange}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                                <option value="">No template</option>
                                {templates.map(t => <option key={t.id} value={t.id}>{t.name}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Link to Performance Review</label>
                            <select value={form.data.performance_review_id} onChange={e => form.setData('performance_review_id', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500"
                                disabled={!form.data.member_id}>
                                <option value="">No linked review</option>
                                {filteredReviews.map(r => <option key={r.id} value={r.id}>{r.title}</option>)}
                            </select>
                        </div>
                    </div>
                    <div>
                        <label className="text-xs text-gray-500 font-medium">Wins</label>
                        <textarea value={form.data.wins} onChange={e => form.setData('wins', e.target.value)} rows={2}
                            placeholder="What went well this week?"
                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 resize-none" />
                    </div>
                    <div>
                        <label className="text-xs text-gray-500 font-medium">Challenges</label>
                        <textarea value={form.data.challenges} onChange={e => form.setData('challenges', e.target.value)} rows={2}
                            placeholder="What's blocking progress?"
                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 resize-none" />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Mood</label>
                            <select value={form.data.mood} onChange={e => form.setData('mood', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                                <option value="">Not set</option>
                                {Object.entries(MOOD_CONFIG).map(([k, v]) => <option key={k} value={k}>{v.label}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Next Meeting</label>
                            <input type="date" value={form.data.next_meeting_date} onChange={e => form.setData('next_meeting_date', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                        </div>
                    </div>
                    <div>
                        <div className="flex items-center justify-between mb-2">
                            <label className="text-xs text-gray-500 font-medium">Action Items</label>
                            <Button type="button" onClick={addItem} variant="ghost" size="sm" >+ Add</Button>
                        </div>
                        {actionItems.map((item, i) => (
                            <div key={i} className="flex items-center gap-2 mb-2">
                                <input type="text" placeholder="Action item…" value={item.text}
                                    onChange={e => setActionItems(prev => prev.map((a, j) => j === i ? { ...a, text: e.target.value } : a))}
                                    className="flex-1 border border-gray-300 rounded-lg px-2 py-1.5 text-xs focus:ring-1 focus:ring-indigo-400" />
                                <input type="date" value={item.due_date}
                                    onChange={e => setActionItems(prev => prev.map((a, j) => j === i ? { ...a, due_date: e.target.value } : a))}
                                    className="w-32 border border-gray-300 rounded-lg px-2 py-1.5 text-xs focus:ring-1 focus:ring-indigo-400" />
                                <Button type="button" onClick={() => setActionItems(prev => prev.filter((_, j) => j !== i))}
                                    className="text-gray-400 hover:text-rose-500"><X className="w-3.5 h-3.5" /></Button>
                            </div>
                        ))}
                    </div>
                    <div className="flex justify-end gap-2 pt-2">
                        <Button type="button" onClick={onClose} variant="ghost" >Cancel</Button>
                        <Button type="submit" disabled={!form.data.member_id || !form.data.meeting_date}
                            className="disabled:opacity-50" >
                            Save Note
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    );
}

export default function OneOnOneIndex({ teamMembers, notes, latestByMember, performanceReviews, templates }: Props) {
    const [selectedMember, setSelectedMember] = useState<string | null>(null);
    const [addOpen, setAddOpen] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');

    const memberNotes = selectedMember
        ? notes.filter(n => 
            n.member.id === selectedMember && 
            (
                (n.wins || '').toLowerCase().includes(searchQuery.toLowerCase()) ||
                (n.challenges || '').toLowerCase().includes(searchQuery.toLowerCase()) ||
                n.action_items.some(a => a.text.toLowerCase().includes(searchQuery.toLowerCase()))
            )
        )
        : [];

    const latestMap: Record<string, Note> = {};
    latestByMember.forEach(n => { latestMap[n.member.id] = n; });

    return (
        <SyncsLayout>
            <Head title="1:1 Notes" />
            <div className="max-w-5xl mx-auto px-4 py-6">
                <div className="flex items-center justify-between mb-6">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">1:1 Notes</h1>
                        <p className="text-sm text-gray-500 mt-0.5">Weekly check-ins with your team</p>
                    </div>
                    <div className="flex items-center gap-3">
                        <div className="relative">
                            <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 w-4 h-4" />
                            <input
                                type="text"
                                placeholder="Search notes..."
                                value={searchQuery}
                                onChange={e => setSearchQuery(e.target.value)}
                                className="pl-9 pr-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 w-64"
                            />
                        </div>
                        <Button onClick={() => setAddOpen(true)}
                            className="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                            <Plus className="w-4 h-4" /> Add Note
                        </Button>
                    </div>
                </div>

                <div className="flex gap-5">
                    {/* Left sidebar */}
                    <div className="w-52 shrink-0">
                        <p className="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3">Team Members</p>
                        <div className="space-y-1">
                            {teamMembers.length === 0 && (
                                <p className="text-xs text-gray-400">No team members</p>
                            )}
                            {teamMembers.map(member => {
                                const latest = latestMap[member.id];
                                const mood = latest?.mood ? MOOD_CONFIG[latest.mood] : null;
                                return (
                                    <Button key={member.id} onClick={() => setSelectedMember(selectedMember === member.id ? null : member.id)}
                                        className={cn('w-full flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-left transition-colors',
                                            selectedMember === member.id
                                                ? 'bg-indigo-600 text-white'
                                                : 'bg-white border border-gray-200 hover:border-indigo-200 text-gray-700'
                                        )}>
                                        <div className={cn('w-7 h-7 rounded-lg flex items-center justify-center text-xs font-bold shrink-0',
                                            selectedMember === member.id ? 'bg-white/20 text-white' : 'bg-indigo-100 text-indigo-700'
                                        )}>
                                            {member.name[0]}
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <p className="text-xs font-semibold truncate">{member.name}</p>
                                            {latest && <p className={cn('text-[10px] truncate', selectedMember === member.id ? 'text-white/70' : 'text-gray-400')}>
                                                {latest.meeting_date}
                                            </p>}
                                        </div>
                                        {mood && <span className={cn('w-2 h-2 rounded-full shrink-0', mood.dot)} />}
                                    </Button>
                                );
                            })}
                        </div>
                    </div>

                    {/* Right panel */}
                    <div className="flex-1 min-w-0">
                        {!selectedMember ? (
                            <div className="bg-white rounded-xl border border-gray-200 py-16 text-center">
                                <Users2 className="w-10 h-10 text-gray-200 mx-auto mb-3" />
                                <p className="text-sm text-gray-400">Select a team member to view their 1:1 history.</p>
                            </div>
                        ) : memberNotes.length === 0 ? (
                            <div className="bg-white rounded-xl border border-gray-200 py-16 text-center">
                                <p className="text-sm text-gray-400">No 1:1 notes yet for this team member.</p>
                                <Button onClick={() => setAddOpen(true)} className="mt-2 text-sm text-indigo-600 font-medium">
                                    Add the first note →
                                </Button>
                            </div>
                        ) : (
                            <div className="space-y-3">
                                {memberNotes.map((note, idx) => (
                                    <NoteCard key={note.id} note={note} defaultExpanded={idx === 0} />
                                ))}
                            </div>
                        )}
                    </div>
                </div>

                {addOpen && <AddNoteModal teamMembers={teamMembers} performanceReviews={performanceReviews} templates={templates} onClose={() => setAddOpen(false)} />}
            </div>
        </SyncsLayout>
    );
}
