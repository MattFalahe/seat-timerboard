@extends('web::layouts.grids.12')

@section('title', 'Timerboard Sync')
@section('page_header', 'Timerboard Sync')

@section('content')
    <div class="card mb-4">
        <div class="card-header">
            <h3 class="card-title">What This Does</h3>
        </div>
        <div class="card-body">
            <p class="mb-2">
                Timerboard Sync lets this SeAT instance share timers with other SeAT installs and with AllianceAuth timer APIs.
            </p>
            <p class="mb-2">
                This is useful when you work with friendly groups, coalition partners, or alts on separate SeAT installs and want everyone to see the same relevant structure timers for joint operations without manually re-entering them.
            </p>
            <p class="mb-2">
                Sync is peer-to-peer, not a mesh. This SeAT only sends timers to the remote instances you explicitly configure here, and remote timers do not get forwarded onward to other peers.
            </p>
            <p class="mb-2">
                Outbound sync is filtered per peer. You can include timers with certain tags, exclude timers with certain tags, or leave both lists empty to send everything.
            </p>
            <p class="mb-0 text-muted">
                SeAT peers are push-based. AAuth peers can also be polled for remote timers if you enable pull. Timers received from another source are marked locally as remote synced and use the receiving role you choose for that peer.
            </p>

            <hr>

            <h5 class="mb-2">How To Create The API Token</h5>
            <ol class="mb-0 pl-3">
                <li>For a SeAT peer, create a SeAT API token on the remote instance and restrict it with an IP allowlist.</li>
                <li>For an AAuth peer, create the API token expected by that AAuth REST API installation.</li>
                <li>Paste that token here as the Peer API Token.</li>
                <li>For SeAT peers, use the remote SeAT base URL plus its instance UUID.</li>
                <li>For AAuth peers, use the full timer API collection endpoint URL if their API exposes timers on a dedicated endpoint.</li>
            </ol>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h3 class="card-title">Local Instance</h3>
        </div>
        <div class="card-body">
            <p class="mb-2"><strong>Name:</strong> {{ $localInstanceName }}</p>
            <p class="mb-2"><strong>Base URL:</strong> <code>{{ $localBaseUrl }}</code></p>
            <p class="mb-2"><strong>Instance UUID:</strong> <code>{{ $localInstanceUuid }}</code></p>
            <p class="mb-0 text-muted">
                Use this UUID on other SeAT instances when they configure this server as a sync peer. Remote access should use a SeAT API token with a strict source IP allowlist.
            </p>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h3 class="card-title">Add Sync Peer</h3>
        </div>
        <div class="card-body">
            <form action="{{ route('timerboard.sync.store') }}" method="POST">
                {{ csrf_field() }}

                <div class="form-row">
                    <div class="form-group col-md-3">
                        <label for="peer_type">Peer Type</label>
                        <select class="form-control sync-peer-type" id="peer_type" name="peer_type" data-target="create-peer-form" required>
                            <option value="seat">SeAT</option>
                            <option value="aauth">AAuth</option>
                        </select>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="peer_name">Peer Name</label>
                        <input type="text" class="form-control" id="peer_name" name="name" required>
                    </div>
                    <div class="form-group col-md-5">
                        <label for="peer_base_url">Peer URL / Endpoint</label>
                        <input type="url" class="form-control" id="peer_base_url" name="base_url" placeholder="https://seat.example.com" required>
                        <small class="text-muted d-block" data-peer-type-scope="seat" data-target="create-peer-form">
                            For SeAT, enter the remote SeAT base URL.
                        </small>
                        <small class="text-muted d-none" data-peer-type-scope="aauth" data-target="create-peer-form">
                            For AAuth, enter the timer API collection endpoint URL used for create/list requests.
                        </small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-4" data-peer-type-scope="seat" data-target="create-peer-form">
                        <label for="peer_instance_uuid">Peer Instance UUID</label>
                        <input type="text" class="form-control" id="peer_instance_uuid" name="instance_uuid" required data-seat-required="1">
                    </div>
                    <div class="form-group col-md-6">
                        <label for="peer_api_token">Peer API Token</label>
                        <input type="text" class="form-control" id="peer_api_token" name="api_token" required>
                        <small class="text-muted" data-target="create-peer-form">
                            This token is used to authenticate to the remote peer API for timer sync.
                        </small>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="incoming_role_id">Default Receiving Role</label>
                        <select name="incoming_role_id" id="incoming_role_id" class="form-control">
                            <option value="">Public (Everyone)</option>
                            @foreach($roles as $role)
                                <option value="{{ $role->id }}">{{ $role->title }}</option>
                            @endforeach
                        </select>
                        <small class="text-muted">Timers received from this peer will use this local role.</small>
                    </div>
                </div>

                <div class="form-group">
                    <label for="sync_tag_ids">Include Timers With Any Of These Tags</label>
                    <select name="sync_tag_ids[]" id="sync_tag_ids" class="form-control select2" multiple style="width: 100%;">
                        @foreach($tags as $tag)
                            <option value="{{ $tag->id }}">{{ $tag->name }}</option>
                        @endforeach
                    </select>
                    <small class="text-muted">Leave empty to allow all timers unless they are excluded below.</small>
                </div>

                <div class="form-group">
                    <label for="excluded_tag_ids">Exclude Timers With Any Of These Tags</label>
                    <select name="excluded_tag_ids[]" id="excluded_tag_ids" class="form-control select2" multiple style="width: 100%;">
                        @foreach($tags as $tag)
                            <option value="{{ $tag->id }}">{{ $tag->name }}</option>
                        @endforeach
                    </select>
                    <small class="text-muted">Exclude rules win if a timer matches both include and exclude lists.</small>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-4" data-peer-type-scope="seat" data-target="create-peer-form">
                        <div class="custom-control custom-switch mt-2">
                            <input type="checkbox" class="custom-control-input" id="allow_remote_delete" name="allow_remote_delete" value="1" checked>
                            <label class="custom-control-label" for="allow_remote_delete">Remote instance decides delete</label>
                        </div>
                    </div>
                    <div class="form-group col-md-4 d-none" data-peer-type-scope="aauth" data-target="create-peer-form">
                        <div class="custom-control custom-switch mt-2">
                            <input type="checkbox" class="custom-control-input" id="pull_remote_timers" name="pull_remote_timers" value="1">
                            <label class="custom-control-label" for="pull_remote_timers">Pull remote timers from AAuth</label>
                        </div>
                    </div>
                    <div class="form-group col-md-4">
                        <div class="custom-control custom-switch mt-2">
                            <input type="checkbox" class="custom-control-input" id="is_enabled" name="is_enabled" value="1" checked>
                            <label class="custom-control-label" for="is_enabled">Peer enabled</label>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Add Peer</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Configured Peers</h3>
        </div>
        <div class="card-body">
            @if($peers->isEmpty())
                <p class="text-muted mb-0">No sync peers configured yet.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Peer URL / Endpoint</th>
                                <th>Instance UUID</th>
                                <th>Include Tags</th>
                                <th>Exclude Tags</th>
                                <th>Pull</th>
                                <th>Receiving Role</th>
                                <th>Delete Control</th>
                                <th>Enabled</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($peers as $peer)
                                @php($peerTagIds = collect($peer->sync_tag_ids)->map(fn ($id) => (int) $id)->all())
                                @php($peerExcludedTagIds = collect($peer->excluded_tag_ids)->map(fn ($id) => (int) $id)->all())
                                <tr>
                                    <td>{{ $peer->name }}</td>
                                    <td>{{ strtoupper($peer->peer_type ?: 'seat') }}</td>
                                    <td><code>{{ $peer->base_url }}</code></td>
                                    <td>
                                        @if($peer->isSeatPeer())
                                            <code>{{ $peer->instance_uuid }}</code>
                                        @else
                                            <span class="text-muted">Internal ID</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if(empty($peerTagIds))
                                            <span class="text-muted">All</span>
                                        @else
                                            {{ $tags->whereIn('id', $peerTagIds)->pluck('name')->implode(', ') }}
                                        @endif
                                    </td>
                                    <td>
                                        @if(empty($peerExcludedTagIds))
                                            <span class="text-muted">None</span>
                                        @else
                                            {{ $tags->whereIn('id', $peerExcludedTagIds)->pluck('name')->implode(', ') }}
                                        @endif
                                    </td>
                                    <td>{{ $peer->isAauthPeer() ? ($peer->pull_remote_timers ? 'Yes' : 'No') : 'N/A' }}</td>
                                    <td>{{ optional($peer->incomingRole)->title ?? 'Public' }}</td>
                                    <td>{{ $peer->isSeatPeer() ? ($peer->allow_remote_delete ? 'Remote can delete' : 'Local keeps copies') : 'Not used' }}</td>
                                    <td>{{ $peer->is_enabled ? 'Yes' : 'No' }}</td>
                                    <td class="text-nowrap">
                                        <button type="button" class="btn btn-warning btn-xs" data-toggle="modal" data-target="#editPeerModal-{{ $peer->id }}">Edit</button>
                                        <form action="{{ route('timerboard.sync.destroy', $peer->id) }}" method="POST" class="d-inline-block" onsubmit="return confirm('Delete this sync peer?');">
                                            {{ csrf_field() }}
                                            {{ method_field('DELETE') }}
                                            <button type="submit" class="btn btn-danger btn-xs">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    @foreach($peers as $peer)
        @php($peerTagIds = collect($peer->sync_tag_ids)->map(fn ($id) => (int) $id)->all())
        @php($peerExcludedTagIds = collect($peer->excluded_tag_ids)->map(fn ($id) => (int) $id)->all())
        <div class="modal fade" id="editPeerModal-{{ $peer->id }}" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit {{ $peer->name }}</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <form action="{{ route('timerboard.sync.update', $peer->id) }}" method="POST">
                        {{ csrf_field() }}
                        {{ method_field('POST') }}

                        <div class="modal-body">
                            <div class="form-row">
                                <div class="form-group col-md-3">
                                    <label>Peer Type</label>
                                    <select name="peer_type" class="form-control sync-peer-type" data-target="edit-peer-form-{{ $peer->id }}">
                                        <option value="seat" {{ $peer->isSeatPeer() ? 'selected' : '' }}>SeAT</option>
                                        <option value="aauth" {{ $peer->isAauthPeer() ? 'selected' : '' }}>AAuth</option>
                                    </select>
                                </div>
                                <div class="form-group col-md-4">
                                    <label>Name</label>
                                    <input type="text" class="form-control" name="name" value="{{ $peer->name }}" required>
                                </div>
                                <div class="form-group col-md-5">
                                    <label>Peer URL / Endpoint</label>
                                    <input type="url" class="form-control" name="base_url" value="{{ $peer->base_url }}" required>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-4 {{ $peer->isSeatPeer() ? '' : 'd-none' }}" data-peer-type-scope="seat" data-target="edit-peer-form-{{ $peer->id }}">
                                    <label>Peer Instance UUID</label>
                                    <input type="text" class="form-control" name="instance_uuid" value="{{ $peer->instance_uuid }}" required data-seat-required="1">
                                </div>
                                <div class="form-group col-md-6">
                                    <label>Peer API Token</label>
                                    <input type="text" class="form-control" name="api_token" placeholder="Leave blank to keep current token">
                                    <small class="text-muted">
                                        This token is used to authenticate to the remote peer API for timer sync.
                                    </small>
                                </div>
                                <div class="form-group col-md-6">
                                    <label>Default Receiving Role</label>
                                    <select name="incoming_role_id" class="form-control">
                                        <option value="">Public (Everyone)</option>
                                        @foreach($roles as $role)
                                            <option value="{{ $role->id }}" {{ (int) $peer->incoming_role_id === (int) $role->id ? 'selected' : '' }}>
                                                {{ $role->title }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Include Tags</label>
                                <select name="sync_tag_ids[]" class="form-control select2" multiple style="width: 100%;">
                                    @foreach($tags as $tag)
                                        <option value="{{ $tag->id }}" {{ in_array((int) $tag->id, $peerTagIds, true) ? 'selected' : '' }}>
                                            {{ $tag->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Exclude Tags</label>
                                <select name="excluded_tag_ids[]" class="form-control select2" multiple style="width: 100%;">
                                    @foreach($tags as $tag)
                                        <option value="{{ $tag->id }}" {{ in_array((int) $tag->id, $peerExcludedTagIds, true) ? 'selected' : '' }}>
                                            {{ $tag->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="custom-control custom-switch mb-2 {{ $peer->isSeatPeer() ? '' : 'd-none' }}" data-peer-type-scope="seat" data-target="edit-peer-form-{{ $peer->id }}">
                                <input type="checkbox" class="custom-control-input" id="allow_remote_delete_{{ $peer->id }}" name="allow_remote_delete" value="1" {{ $peer->allow_remote_delete ? 'checked' : '' }}>
                                <label class="custom-control-label" for="allow_remote_delete_{{ $peer->id }}">Remote instance decides delete</label>
                            </div>
                            <div class="custom-control custom-switch mb-2 {{ $peer->isAauthPeer() ? '' : 'd-none' }}" data-peer-type-scope="aauth" data-target="edit-peer-form-{{ $peer->id }}">
                                <input type="checkbox" class="custom-control-input" id="pull_remote_timers_{{ $peer->id }}" name="pull_remote_timers" value="1" {{ $peer->pull_remote_timers ? 'checked' : '' }}>
                                <label class="custom-control-label" for="pull_remote_timers_{{ $peer->id }}">Pull remote timers from AAuth</label>
                            </div>
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="is_enabled_{{ $peer->id }}" name="is_enabled" value="1" {{ $peer->is_enabled ? 'checked' : '' }}>
                                <label class="custom-control-label" for="is_enabled_{{ $peer->id }}">Peer enabled</label>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
@endsection

@push('javascript')
    <script>
        $(function () {
            $('.select2').select2({
                width: '100%'
            });

            function updatePeerTypeSections($select) {
                var peerType = $select.val();
                var target = $select.data('target');
                var $scope = target ? $('[data-target="' + target + '"]') : $(document);

                $scope.each(function () {
                    var $section = $(this);
                    var sectionType = $section.data('peer-type-scope');

                    if (!sectionType) {
                        return;
                    }

                    if (sectionType === peerType) {
                        $section.removeClass('d-none');
                    } else {
                        $section.addClass('d-none');
                    }
                });

                var $instanceUuidInputs = $scope.find('input[name="instance_uuid"][data-seat-required="1"]');
                $instanceUuidInputs.prop('required', peerType === 'seat');
            }

            $('.sync-peer-type').each(function () {
                updatePeerTypeSections($(this));
            }).on('change', function () {
                updatePeerTypeSections($(this));
            });
        });
    </script>
@endpush
