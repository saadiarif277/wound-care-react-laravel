<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ContactsController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:view-contacts')->only(['index', 'show']);
        $this->middleware('permission:create-contacts')->only(['create', 'store']);
        $this->middleware('permission:edit-contacts')->only(['edit', 'update']);
        $this->middleware('permission:delete-contacts')->only('destroy');
    }

    /**
     * Display a listing of the contacts.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $contacts = Contact::orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 20));
            
        return response()->json([
            'contacts' => $contacts->items(),
            'pagination' => [
                'current_page' => $contacts->currentPage(),
                'last_page' => $contacts->lastPage(),
                'per_page' => $contacts->perPage(),
                'total' => $contacts->total(),
            ]
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:contacts',
            'phone' => 'required|string|max:20',
        ]);

        $contact = Contact::create($validated);
        return response()->json(['contact' => $contact], Response::HTTP_CREATED);
    }

    public function show(Contact $contact)
    {
        return response()->json(['contact' => $contact]);
    }

    public function update(Request $request, Contact $contact)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:contacts,email,' . $contact->id,
            'phone' => 'sometimes|string|max:20',
        ]);

        $contact->update($validated);
        return response()->json(['contact' => $contact]);
    }

    public function destroy(Contact $contact)
    {
        $contact->delete();
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
