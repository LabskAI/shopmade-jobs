<div class="grid gap-4 sm:grid-cols-2">
    <x-field label="Customer" for="customer_id" :error="$errors->first('customer_id')">
        <x-select
            id="customer_id"
            name="customer_id"
            required
            :options="$customers->pluck('name', 'id')"
            :selected="old('customer_id', $job->customer_id ?? $selectedCustomerId)"
            :error="$errors->has('customer_id')"
        />
    </x-field>
    <x-field label="Assigned to" for="assigned_to" :error="$errors->first('assigned_to')">
        <x-select
            id="assigned_to"
            name="assigned_to"
            :options="$staff->pluck('name', 'id')"
            :selected="old('assigned_to', $job->assigned_to ?? null)"
            placeholder="Unassigned"
            :error="$errors->has('assigned_to')"
        />
    </x-field>
    <x-field label="Scheduled" for="scheduled_at" :error="$errors->first('scheduled_at')">
        <x-input
            id="scheduled_at"
            type="datetime-local"
            name="scheduled_at"
            value="{{ old('scheduled_at', isset($job) && $job->scheduled_at ? $job->scheduled_at->format('Y-m-d\\TH:i') : '') }}"
            :error="$errors->has('scheduled_at')"
        />
    </x-field>
    <x-field label="Ends" for="scheduled_end_at" :error="$errors->first('scheduled_end_at')" hint="Optional end of the visit window.">
        <x-input
            id="scheduled_end_at"
            type="datetime-local"
            name="scheduled_end_at"
            value="{{ old('scheduled_end_at', isset($job) && $job->scheduled_end_at ? $job->scheduled_end_at->format('Y-m-d\\TH:i') : '') }}"
            :error="$errors->has('scheduled_end_at')"
        />
    </x-field>
    <x-field label="Status" for="status" :error="$errors->first('status')">
        <x-select
            id="status"
            name="status"
            required
            :options="collect($statuses)->mapWithKeys(fn ($status) => [$status->value => $status->label()])"
            :selected="old('status', $job->status->value ?? 'scheduled')"
            :error="$errors->has('status')"
        />
    </x-field>
    <x-field class="sm:col-span-2" label="Notes" for="notes" :error="$errors->first('notes')">
        <x-textarea id="notes" name="notes" rows="4" :error="$errors->has('notes')">{{ old('notes', $job->notes ?? '') }}</x-textarea>
    </x-field>
</div>
